<?php

namespace App\Filament\Pages;

use App\Actions\CreateSuccessor;
use App\Actions\DeleteInvestment;
use App\Actions\PreviewInvestment;
use App\Actions\PreviewSuccessor;
use App\Actions\SaveInvestment;
use App\Models\Investment;
use App\Models\Plan;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class PlanDetail extends Page
{
    protected string $view = 'filament.pages.plan-detail';

    protected static ?string $slug = 'pac/{plan}';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $planId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $investmentInput = [];

    /** @var array<string, int|string|bool> */
    #[Locked]
    public array $investmentSnapshot = [];

    #[Locked]
    public ?string $investmentFingerprint = null;

    #[Locked]
    public ?int $editingInvestmentId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $successorInput = [];

    /** @var array<string, int|string> */
    #[Locked]
    public array $successorPreview = [];

    #[Locked]
    public ?string $successorFingerprint = null;

    #[Locked]
    public ?string $deleteInvestmentFingerprint = null;

    public function mount(int $plan): void
    {
        $this->planId = $plan;
        $this->plan();

        if (request()->boolean('record')) {
            $this->defaultAction = 'previewInvestment';
        }
    }

    public function getHeading(): string
    {
        $plan = $this->plan();

        return $plan->instrument->ticker.' · '.$plan->instrument->name;
    }

    public function plan(): Plan
    {
        return Plan::query()
            ->whereBelongsTo(Auth::user())
            ->with(['instrument', 'investments', 'latestInvestment', 'previousPlan', 'successor.instrument'])
            ->findOrFail($this->planId);
    }

    /** @return Collection<int, Investment> */
    public function investments(): Collection
    {
        return $this->plan()->investments->sortByDesc(fn (Investment $investment): string => $investment->period->toDateString())->values();
    }

    /** @return array<array{period: string, target: int, actual: ?int, investment: ?Investment}> */
    public function chartPoints(): array
    {
        return $this->chartPointsFor($this->plan());
    }

    protected function getViewData(): array
    {
        $plan = $this->plan();
        $latest = $plan->latestInvestment;
        $displayPrice = $plan->status === 'closed' ? $latest?->unit_price : $plan->instrument->latest_price;
        $displayPriceDate = $plan->status === 'closed' ? $latest?->price_date : $plan->instrument->latest_price_date;
        $quantity = $plan->quantity();
        $portfolioValue = $quantity === 0 ? 0 : ($displayPrice === null ? null : Decimal::value($quantity, Decimal::price($displayPrice)));
        $targetForPeriod = $plan->status === 'closed' ? $plan->closed_period->toDateString() : now()->startOfMonth()->toDateString();
        $started = $targetForPeriod >= $plan->start_period->toDateString()
            && ($plan->opening_period === null || $targetForPeriod >= $plan->opening_period->toDateString());

        return [
            'plan' => $plan,
            'investments' => $plan->investments->sortByDesc(fn (Investment $investment): string => $investment->period->toDateString())->values(),
            'points' => $this->chartPointsFor($plan),
            'latest' => $latest,
            'displayPrice' => $displayPrice,
            'displayPriceDate' => $displayPriceDate,
            'portfolioValue' => $portfolioValue,
            'targetForPeriod' => $targetForPeriod,
            'calendarTarget' => $started ? $plan->targetFor($targetForPeriod) : null,
        ];
    }

    /** @return array<array{period: string, target: int, actual: ?int, investment: ?Investment}> */
    private function chartPointsFor(Plan $plan): array
    {
        $investments = $plan->investments->keyBy(fn (Investment $investment): string => $investment->period->format('Y-m'));
        $first = $plan->opening_period?->format('Y-m') ?? $plan->start_period->format('Y-m');

        if ($plan->status === 'active' && $first > now()->format('Y-m')) {
            return [];
        }

        $last = $plan->status === 'closed'
            ? $plan->closed_period?->format('Y-m') ?? $investments->keys()->max() ?? $first
            : max(now()->format('Y-m'), $investments->keys()->max() ?? $first);

        if ($last < $first) {
            $last = $first;
        }

        $points = [];
        $cursor = Carbon::createFromFormat('!Y-m', $first);
        $lastDate = Carbon::createFromFormat('!Y-m', $last);

        $monthsShown = $cursor && $lastDate
            ? ((int) $lastDate->format('Y') - (int) $cursor->format('Y')) * 12 + (int) $lastDate->format('n') - (int) $cursor->format('n')
            : 0;

        if ($cursor && $lastDate && $monthsShown > 179) {
            $cursor = $lastDate->copy()->subMonths(179)->startOfMonth();
        }

        while ($cursor && $lastDate && $cursor <= $lastDate && count($points) < 180) {
            $period = $cursor->format('Y-m');
            $investment = $investments->get($period);
            $points[] = [
                'period' => $period,
                'target' => $plan->targetFor($period.'-01'),
                'actual' => $investment?->getRawOriginal('portfolio_value_before') === null
                    ? null
                    : (int) $investment->getRawOriginal('portfolio_value_before'),
                'investment' => $investment,
            ];
            $cursor->addMonth();
        }

        return $points;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        $plan = $this->plan();
        $actions = [
            Action::make('previewInvestment')
                ->label('Registra mese')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->visible(fn (): bool => $plan->status === 'active')
                ->modalHeading('Anteprima della rilevazione')
                ->modalDescription('Inserisci i dati del mese. L’anteprima non salva una rilevazione e non cambia i saldi.')
                ->modalSubmitActionLabel('Mostra anteprima')
                ->fillForm(fn (): array => [
                    'period' => substr($plan->firstRecordablePeriod(), 0, 7),
                    'unit_price' => $plan->instrument->latest_price,
                    'price_date' => now()->toDateString(),
                ])
                ->schema($this->investmentSchema())
                ->action(fn (array $data) => $this->previewNewInvestment($data)),
        ];

        if ($plan->status === 'active') {
            $actions[] = Action::make('manageQuote')
                ->label('Quotazione')
                ->icon(Heroicon::ArrowTrendingUp)
                ->color('gray')
                ->url(Instruments::getUrl([
                    'action' => 'updateQuote',
                    'actionArguments' => ['instrument' => $plan->instrument->getKey()],
                ]));

            if ($plan->instrument->latest_price !== null) {
                $actions[] = Action::make('correctQuote')
                    ->label('Correggi quotazione')
                    ->icon(Heroicon::Pencil)
                    ->color('warning')
                    ->url(Instruments::getUrl([
                        'action' => 'correctQuote',
                        'actionArguments' => ['instrument' => $plan->instrument->getKey()],
                    ]));
            }
        }

        if ($plan->configurationEditable()) {
            $actions[] = Action::make('editConfiguration')
                ->label('Configurazione')
                ->icon(Heroicon::PencilSquare)
                ->color('gray')
                ->url(Plans::getUrl([
                    'action' => 'editConfiguration',
                    'actionArguments' => ['plan' => $plan->getKey()],
                ]));
        }

        if ($plan->deletable()) {
            $actions[] = Action::make('deletePlan')
                ->label('Elimina PAC')
                ->icon(Heroicon::Trash)
                ->color('danger')
                ->url(Plans::getUrl([
                    'action' => 'deletePlan',
                    'actionArguments' => ['plan' => $plan->getKey()],
                ]));
        }

        if ($plan->status === 'active' && $plan->successor === null && $this->firstCompatibleSuccessorPeriod($plan) <= now()->format('Y-m')) {
            $actions[] = Action::make('previewSuccessor')
                ->label('Crea PAC successore')
                ->icon(Heroicon::ArrowPathRoundedSquare)
                ->color('gray')
                ->modalHeading('Imposta il PAC successore')
                ->modalDescription('Il riepilogo non modifica il piano corrente. La decorrenza deve essere successiva e non futura.')
                ->modalSubmitActionLabel('Mostra riepilogo')
                ->fillForm(fn (): array => [
                    'monthly_increment' => Decimal::formatMoney((int) $plan->getRawOriginal('monthly_increment')),
                    'start_period' => $this->defaultSuccessorStartPeriod($plan),
                ])
                ->schema([
                    Instruments::decimalField('monthly_increment', 'Nuovo incremento mensile (€)')->required(),
                    TextInput::make('start_period')->label('Mese di decorrenza')->required()->type('month')->helperText('Il passaggio è immediato e non può avere una decorrenza futura.'),
                ])
                ->action(fn (array $data) => $this->previewSuccessor($data));
        }

        return $actions;
    }

    public function saveInvestmentAction(): Action
    {
        return Action::make('saveInvestment')
            ->label('Conferma e salva')
            ->icon(Heroicon::Check)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Confermare la rilevazione?')
            ->modalDescription('Salva la fotografia del calcolo e le quote effettivamente acquistate. Questa operazione registra un acquisto già concluso e non invia ordini al broker.')
            ->modalSubmitActionLabel('Conferma rilevazione')
            ->visible(fn (): bool => $this->investmentSnapshot !== [])
            ->action(function (): void {
                $plan = $this->plan();

                try {
                    app(SaveInvestment::class)->handle(
                        Auth::user(),
                        $plan,
                        $this->investmentInput,
                        $this->investmentFingerprint ?? '',
                        $this->editingInvestment(),
                    );
                } catch (ValidationException $exception) {
                    if (array_key_exists('expected_fingerprint', $exception->errors())) {
                        $this->refreshInvestmentPreview();

                        Notification::make()->title('Il PAC è cambiato')->body('Abbiamo aggiornato l’anteprima mantenendo i dati inseriti. Verificala e conferma di nuovo.')->warning()->send();

                        return;
                    }

                    throw $exception;
                }

                $this->clearInvestmentPreview();
                Notification::make()->title('Rilevazione salvata')->success()->send();
                $this->redirect(PlanDetail::getUrl(['plan' => $plan->getKey()]));
            });
    }

    public function adjustInvestmentAction(): Action
    {
        return Action::make('adjustInvestment')
            ->label('Modifica quote effettive')
            ->icon(Heroicon::PencilSquare)
            ->color('gray')
            ->visible(fn (): bool => $this->investmentSnapshot !== [])
            ->fillForm(fn (): array => ['purchased_quantity' => $this->investmentInput['purchased_quantity'] ?? $this->investmentSnapshot['proposed_quantity'] ?? 0])
            ->schema([
                TextInput::make('purchased_quantity')->label('Quote acquistate')->required()->integer()->minValue(0)->helperText('Inserisci l’eseguito effettivo. Zero è valido.'),
            ])
            ->action(function (array $data): void {
                $input = $this->investmentInput;
                $input['purchased_quantity'] = $data['purchased_quantity'];
                $this->previewInvestmentData($input, $this->editingInvestment());
            });
    }

    public function editLatestInvestmentAction(): Action
    {
        return Action::make('editLatestInvestment')
            ->label('Correggi ultima rilevazione')
            ->icon(Heroicon::PencilSquare)
            ->color('gray')
            ->modalHeading('Correggi l’ultima rilevazione')
            ->modalDescription('Puoi correggere solo prezzo e quote effettive. Periodo e data del prezzo restano quelli registrati.')
            ->modalSubmitActionLabel('Mostra anteprima corretta')
            ->fillForm(fn (array $arguments): array => $this->latestInvestmentFormData((int) $arguments['investment']))
            ->schema([
                Instruments::decimalField('unit_price', 'Prezzo unitario (€)', 6)->required(),
                TextInput::make('purchased_quantity')->label('Quote acquistate')->required()->integer()->minValue(0),
            ])
            ->action(function (array $data, array $arguments): void {
                $investment = $this->latestInvestment((int) $arguments['investment']);
                $this->editingInvestmentId = (int) $investment->getKey();
                $this->previewInvestmentData($data, $investment);
            });
    }

    public function deleteLatestInvestmentAction(): Action
    {
        return Action::make('deleteLatestInvestment')
            ->label('Elimina ultima rilevazione')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Eliminare questa rilevazione?')
            ->modalDescription(fn (array $arguments): string => $this->deleteInvestmentDescription((int) $arguments['investment']))
            ->modalSubmitActionLabel('Elimina rilevazione')
            ->action(function (array $arguments): void {
                $investment = $this->latestInvestment((int) $arguments['investment']);
                try {
                    app(DeleteInvestment::class)->handle(Auth::user(), $investment, $this->deleteInvestmentFingerprint ?? '');
                } catch (ValidationException $exception) {
                    if (array_key_exists('expected_fingerprint', $exception->errors())) {
                        $plan = $this->plan();
                        $this->deleteInvestmentFingerprint = DeleteInvestment::fingerprint($plan, $investment);
                        Notification::make()->title('I saldi sono cambiati')->body('Il riepilogo è stato aggiornato. Controlla di nuovo l’effetto prima di confermare.')->warning()->send();

                        return;
                    }

                    throw $exception;
                }

                $this->deleteInvestmentFingerprint = null;
                Notification::make()->title('Rilevazione eliminata')->success()->send();
                $this->redirect(PlanDetail::getUrl(['plan' => $this->planId]));
            });
    }

    public function adjustSuccessorPreviewAction(): Action
    {
        return Action::make('adjustSuccessorPreview')
            ->label('Rivedi i dati')
            ->icon(Heroicon::PencilSquare)
            ->color('gray')
            ->visible(fn (): bool => $this->successorPreview !== [])
            ->fillForm(fn (): array => $this->successorInput)
            ->schema([
                Instruments::decimalField('monthly_increment', 'Nuovo incremento mensile (€)')->required(),
                TextInput::make('start_period')->label('Mese di decorrenza')->required()->type('month'),
            ])
            ->action(fn (array $data) => $this->previewSuccessor($data));
    }

    public function createSuccessorAction(): Action
    {
        return Action::make('createSuccessor')
            ->label('Conferma passaggio')
            ->icon(Heroicon::ArrowPathRoundedSquare)
            ->color('primary')
            ->visible(fn (): bool => $this->successorPreview !== [])
            ->requiresConfirmation()
            ->modalHeading('Chiudere il PAC e aprire il successore?')
            ->modalDescription('Il passaggio è immediato. La conferma rende non modificabili anche le rilevazioni del piano precedente.')
            ->modalSubmitActionLabel('Conferma passaggio')
            ->action(function (): void {
                try {
                    $successor = app(CreateSuccessor::class)->handle(
                        Auth::user(),
                        $this->plan(),
                        $this->successorInput,
                        $this->successorFingerprint ?? '',
                    );
                } catch (ValidationException $exception) {
                    if (array_key_exists('expected_fingerprint', $exception->errors())) {
                        $this->refreshSuccessorPreview();
                        Notification::make()->title('Il PAC è cambiato')->body('Il riepilogo è stato aggiornato. Controlla i saldi trasferiti e conferma di nuovo.')->warning()->send();

                        return;
                    }

                    throw $exception;
                }

                Notification::make()->title('PAC successore creato')->success()->send();
                $this->redirect(PlanDetail::getUrl(['plan' => $successor->getKey()]));
            });
    }

    /** @return array<TextInput|DatePicker> */
    private function investmentSchema(): array
    {
        return [
            TextInput::make('period')->label('Periodo')->required()->type('month')->default(now()->format('Y-m')),
            Instruments::decimalField('unit_price', 'Prezzo unitario (€)', 6)->required(),
            DatePicker::make('price_date')->label('Data del prezzo')->required()->native(false)->default(now()->toDateString()),
            TextInput::make('purchased_quantity')->label('Quote effettive (facoltativo)')->integer()->minValue(0)->helperText('Se vuoto, l’anteprima propone le quote calcolate dal VA.'),
        ];
    }

    private function previewNewInvestment(array $data): void
    {
        $this->editingInvestmentId = null;
        $this->previewInvestmentData($data);
    }

    private function previewInvestmentData(array $data, ?Investment $editing = null): void
    {
        $data['unit_price'] = $this->decimalInput($data['unit_price'] ?? '');
        $this->investmentInput = $editing
            ? [
                'unit_price' => $data['unit_price'],
                'purchased_quantity' => $data['purchased_quantity'],
            ]
            : [
                'period' => $data['period'],
                'unit_price' => $data['unit_price'],
                'price_date' => $data['price_date'],
                'purchased_quantity' => $data['purchased_quantity'] ?? null,
            ];

        $preview = app(PreviewInvestment::class)->handle(Auth::user(), $this->plan(), $this->investmentInput, $editing);
        $this->investmentFingerprint = $preview['fingerprint'];
        $this->investmentInput['unit_price'] = $preview['unit_price'];
        $this->investmentInput['purchased_quantity'] = $preview['snapshot']['purchased_quantity'];
        $this->investmentInput['period'] = $preview['period'];
        $this->investmentInput['price_date'] = $preview['price_date'];
        $this->investmentSnapshot = $preview['snapshot'];
    }

    private function refreshInvestmentPreview(): void
    {
        $this->previewInvestmentData($this->investmentInput, $this->editingInvestment());
    }

    private function clearInvestmentPreview(): void
    {
        $this->investmentInput = [];
        $this->investmentSnapshot = [];
        $this->investmentFingerprint = null;
        $this->editingInvestmentId = null;
    }

    private function previewSuccessor(array $data): void
    {
        $data['monthly_increment'] = $this->decimalInput($data['monthly_increment'] ?? '');
        $this->successorInput = $data;
        $this->successorPreview = app(PreviewSuccessor::class)->handle(Auth::user(), $this->plan(), $data);
        $this->successorFingerprint = $this->successorPreview['fingerprint'];
    }

    private function refreshSuccessorPreview(): void
    {
        $this->previewSuccessor($this->successorInput);
    }

    private function defaultSuccessorStartPeriod(Plan $plan): string
    {
        $firstPeriod = $this->firstCompatibleSuccessorPeriod($plan);

        return max(now()->format('Y-m'), $firstPeriod);
    }

    public function firstCompatibleSuccessorPeriod(?Plan $plan = null): string
    {
        $plan ??= $this->plan();
        $firstCompatibleMonth = max(
            $plan->start_period->format('Y-m'),
            $plan->opening_period?->format('Y-m') ?? $plan->start_period->format('Y-m'),
            $plan->latestInvestment?->period->format('Y-m') ?? $plan->start_period->format('Y-m'),
        );

        return Carbon::createFromFormat('!Y-m', $firstCompatibleMonth)->addMonth()->format('Y-m');
    }

    /** @return array<string, mixed> */
    private function latestInvestmentFormData(int $id): array
    {
        $investment = $this->latestInvestment($id);

        return [
            'unit_price' => $investment->unit_price,
            'purchased_quantity' => $investment->purchased_quantity,
        ];
    }

    private function latestInvestment(int $id): Investment
    {
        $plan = $this->plan();
        $investment = $plan->investments()->findOrFail($id);
        $latest = $plan->latestInvestment;

        abort_unless($plan->status === 'active' && $latest?->is($investment), 404);

        return $investment;
    }

    private function editingInvestment(): ?Investment
    {
        return $this->editingInvestmentId === null ? null : $this->latestInvestment($this->editingInvestmentId);
    }

    private function deleteInvestmentDescription(int $id): string
    {
        $plan = $this->plan();
        $investment = $this->latestInvestment($id);
        $this->deleteInvestmentFingerprint ??= DeleteInvestment::fingerprint($plan, $investment);

        $previousInvestment = $this->previousInvestment($plan);
        $remainingQuantity = (int) ($previousInvestment?->quantity_after ?? $plan->opening_quantity);
        $remainingInvested = (int) ($previousInvestment?->getRawOriginal('invested_amount_after') ?? $plan->getRawOriginal('opening_invested_amount'));
        $remainingLiquidity = (int) ($previousInvestment?->getRawOriginal('virtual_liquidity_after') ?? $plan->getRawOriginal('opening_virtual_liquidity'));

        return "Eliminare la rilevazione di {$investment->period->format('m/Y')} ({$investment->purchased_quantity} quote a € {$investment->unit_price}, controvalore € ".Decimal::formatMoney((int) $investment->getRawOriginal('purchased_amount')).")? Il mese tornerà non rilevato. Saldi attuali: {$plan->quantity()} quote, capitale investito € ".Decimal::formatMoney($plan->investedAmount()).', liquidità virtuale € '.Decimal::formatMoney($plan->virtualLiquidity()).". Saldi dopo: {$remainingQuantity} quote, capitale investito € ".Decimal::formatMoney($remainingInvested).', liquidità virtuale € '.Decimal::formatMoney($remainingLiquidity).'. Questa operazione corregge i dati nell’app e non annulla acquisti presso il broker. Prezzo e data della quotazione condivisa restano invariati.';
    }

    private function previousInvestment(Plan $plan): ?Investment
    {
        $latestId = $plan->latestInvestment?->getKey();

        return $plan->investments()
            ->when($latestId !== null, fn ($query) => $query->where('id', '!=', $latestId))
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->first();
    }

    private function decimalInput(mixed $value): string
    {
        return str_replace(',', '.', trim((string) $value));
    }
}
