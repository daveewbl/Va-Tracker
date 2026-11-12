<?php

namespace App\Filament\Pages;

use App\Actions\DeletePlan;
use App\Actions\OpenPlan;
use App\Actions\PreviewPlan;
use App\Actions\SaveInstrument;
use App\Actions\UpdatePlanConfiguration;
use App\Models\Instrument;
use App\Models\Plan;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class Plans extends Page
{
    protected string $view = 'filament.pages.plans';

    protected static ?string $slug = 'pacs';

    protected static ?string $title = 'I tuoi PAC';

    protected static ?string $navigationLabel = 'PAC';

    protected static string|\UnitEnum|null $navigationGroup = 'Portafoglio';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /** @var array<string, mixed> */
    #[Locked]
    public array $planInput = [];

    /** @var array<string, int|string> */
    #[Locked]
    public array $planPreview = [];

    #[Locked]
    public ?int $configurationPlanId = null;

    #[Locked]
    public ?string $configurationFingerprint = null;

    #[Locked]
    public ?string $deleteFingerprint = null;

    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->defaultAction = 'openPlan';
        }
    }

    protected function getViewData(): array
    {
        $plans = Plan::query()->where('user_id', Auth::id())
            ->with(['instrument', 'latestInvestment', 'previousPlan', 'successor'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')->orderByDesc('id')->get();
        $summaries = [];

        foreach ($plans as $plan) {
            $quantity = $plan->quantity();
            $last = $plan->latestInvestment;
            $price = $plan->status === 'closed' ? $last?->getRawOriginal('unit_price') : $plan->instrument->getRawOriginal('latest_price');
            $summaries[$plan->id] = [
                'value' => $quantity === 0 ? 0 : ($price === null ? null : Decimal::value($quantity, (int) $price)),
                'editable' => $plan->configurationEditable(),
                'deletable' => $plan->deletable(),
            ];
        }

        return [
            'plans' => $plans,
            'summaries' => $summaries,
            'previewInstrument' => $this->planPreview === [] ? null : Instrument::query()->find($this->planPreview['instrument_id']),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createInstrument')->label('Nuovo titolo')->icon(Heroicon::Plus)->color('gray')
                ->schema(Instruments::instrumentFields())->action(function (array $data): void {
                    SaveInstrument::run(Auth::user(), $data);
                    Notification::make()->title('Titolo aggiunto')->success()->send();
                }),
            Action::make('openPlan')->label('Nuovo PAC')->icon(Heroicon::Plus)
                ->modalHeading('Apri un PAC')->modalSubmitActionLabel('Mostra riepilogo')
                ->fillForm(function (): array {
                    $this->planInput = [];
                    $this->planPreview = [];
                    $this->configurationPlanId = null;
                    $this->configurationFingerprint = null;

                    return ['opening_mode' => 'zero', 'start_period' => now()->format('Y-m'), 'opening_virtual_liquidity' => '0'];
                })
                ->schema($this->configurationFields(false))
                ->action(function (array $data): void {
                    $this->preparePlan($data);
                }),
        ];
    }

    public function editConfigurationAction(): Action
    {
        return Action::make('editConfiguration')->label('Modifica configurazione')->icon(Heroicon::PencilSquare)
            ->modalHeading('Modifica configurazione')->modalSubmitActionLabel('Mostra riepilogo')
            ->fillForm(function (array $arguments): array {
                $plan = $this->ownedPlan((int) $arguments['plan']);
                abort_unless($plan->configurationEditable(), 403);
                $this->planInput = [];
                $this->planPreview = [];
                $this->configurationPlanId = $plan->id;
                $this->configurationFingerprint = $plan->stateFingerprint();

                return [
                    'instrument_id' => $plan->instrument_id,
                    'start_period' => $plan->start_period->format('Y-m'),
                    'opening_mode' => $plan->opening_period === null ? 'zero' : 'existing',
                    'opening_period' => $plan->opening_period?->format('Y-m'),
                    'opening_quantity' => (string) $plan->opening_quantity,
                    'opening_invested_amount' => Decimal::formatMoney((int) $plan->getRawOriginal('opening_invested_amount')),
                    'opening_virtual_liquidity' => Decimal::formatMoney((int) $plan->getRawOriginal('opening_virtual_liquidity')),
                ];
            })
            ->schema($this->configurationFields(true))
            ->action(function (array $data): void {
                $this->preparePlan($data);
            });
    }

    public function confirmPlanAction(): Action
    {
        return Action::make('confirmPlan')->label($this->configurationPlanId === null ? 'Conferma apertura' : 'Conferma modifica')
            ->requiresConfirmation()->modalHeading('Conferma il riepilogo del PAC')
            ->modalDescription(fn (): string => $this->previewDescription())
            ->visible(fn (): bool => $this->planPreview !== [])
            ->action(function (Action $action): void {
                try {
                    if ($this->configurationPlanId === null) {
                        $plan = OpenPlan::run(Auth::user(), $this->planInput);
                    } else {
                        $current = $this->ownedPlan($this->configurationPlanId);
                        $plan = UpdatePlanConfiguration::run(Auth::user(), $current, $this->planInput, $this->configurationFingerprint ?? '');
                    }
                } catch (ValidationException $exception) {
                    if (isset($exception->errors()['expected_fingerprint']) && $this->configurationPlanId !== null) {
                        $current = $this->ownedPlan($this->configurationPlanId);
                        $this->planPreview = PreviewPlan::run(Auth::user(), $this->planInput, $current);
                        $this->configurationFingerprint = $current->stateFingerprint();
                        Notification::make()->title('Configurazione cambiata')->body('Verifica il nuovo riepilogo. I tuoi dati sono conservati; occorre una nuova conferma.')->warning()->persistent()->send();
                        $action->halt();
                    }
                    $this->notifyErrors($exception);
                    throw $exception;
                }

                $this->planPreview = [];
                $this->planInput = [];
                $this->redirect(PlanDetail::getUrl(['plan' => $plan->id]));
            });
    }

    public function deletePlanAction(): Action
    {
        return Action::make('deletePlan')->label('Elimina PAC')->icon(Heroicon::Trash)->color('danger')
            ->requiresConfirmation()->modalHeading('Eliminare questo PAC?')
            ->mountUsing(function (array $arguments): void {
                $this->deleteFingerprint = $this->ownedPlan((int) $arguments['plan'])->stateFingerprint();
            })
            ->modalDescription(function (array $arguments): string {
                $plan = $this->ownedPlan((int) $arguments['plan']);

                return 'PAC '.$plan->instrument->ticker.' da '.$plan->start_period->format('m/Y').': verranno rimossi anche i saldi di apertura ('.$plan->quantity().' quote, '.Decimal::formatMoney($plan->investedAmount()).' € investiti, '.Decimal::formatMoney($plan->virtualLiquidity()).' € virtuali). Il titolo resterà nel catalogo.';
            })
            ->action(function (array $arguments): void {
                try {
                    DeletePlan::run(Auth::user(), $this->ownedPlan((int) $arguments['plan']), $this->deleteFingerprint ?? '');
                } catch (ValidationException $exception) {
                    $this->notifyErrors($exception);
                    throw $exception;
                }
                Notification::make()->title('PAC eliminato')->success()->send();
            });
    }

    /** @return array<Component> */
    private function configurationFields(bool $editing): array
    {
        $existing = fn (Get $get): bool => $get('opening_mode') === 'existing';
        $instrument = Select::make('instrument_id')->label('Titolo')->required()->searchable()
            ->options(fn (): array => Instrument::query()->orderBy('ticker')->get()->mapWithKeys(fn (Instrument $instrument): array => [$instrument->id => $instrument->ticker.' · '.$instrument->name])->all())
            ->createOptionForm(Instruments::instrumentFields())
            ->createOptionUsing(fn (array $data): int => SaveInstrument::run(Auth::user(), $data)->id);
        $fields = [$instrument];

        if (! $editing) {
            $fields[] = Instruments::decimalField('monthly_increment', 'Incremento mensile (€)')->required();
        } else {
            $fields[] = Text::make('L’incremento mensile resta fisso. Per cambiarlo crea un PAC successore.');
        }

        return [
            ...$fields,
            TextInput::make('start_period')->label('Mese di inizio originario')->type('month')->required(),
            Radio::make('opening_mode')->label('Situazione di partenza')->options(['zero' => 'Da zero', 'existing' => 'Con saldo iniziale'])->default('zero')->required()->inline()->live(),
            TextInput::make('opening_period')->label('Ultimo mese già coperto')->type('month')->visible($existing)->required($existing),
            TextInput::make('opening_quantity')->label('Quote possedute')->integer()->minValue(0)->visible($existing)->required($existing),
            Instruments::decimalField('opening_invested_amount', 'Capitale già investito (€)')->visible($existing)->required($existing),
            Instruments::decimalField('opening_virtual_liquidity', 'Liquidità virtuale iniziale (€)')->default('0')->visible($existing)->required($existing),
        ];
    }

    /** @param array<string, mixed> $data */
    private function preparePlan(array $data): void
    {
        try {
            $this->planPreview = PreviewPlan::run(Auth::user(), $data, $this->configurationPlanId === null ? null : $this->ownedPlan($this->configurationPlanId));
        } catch (ValidationException $exception) {
            $this->notifyErrors($exception);
            throw $exception;
        }
        $this->planInput = $data;
    }

    private function previewDescription(): string
    {
        return $this->planPreview === [] ? '' : 'Obiettivo di apertura: '.Decimal::formatMoney($this->planPreview['opening_target_amount']).' €. Primo periodo registrabile: '.substr($this->planPreview['first_period'], 0, 7).', obiettivo '.Decimal::formatMoney($this->planPreview['first_target_amount']).' €. Nessuna rilevazione sarà creata.';
    }

    private function notifyErrors(ValidationException $exception): void
    {
        Notification::make()->title('Controlla i dati')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->persistent()->send();
    }

    private function ownedPlan(int $id): Plan
    {
        return Plan::query()->where('user_id', Auth::id())->with(['instrument', 'latestInvestment'])->findOrFail($id);
    }
}
