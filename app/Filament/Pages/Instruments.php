<?php

namespace App\Filament\Pages;

use App\Actions\DeleteInstrument;
use App\Actions\SaveInstrument;
use App\Actions\UpdateQuote;
use App\Models\Instrument;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class Instruments extends Page
{
    protected string $view = 'filament.pages.instruments';

    protected static ?string $slug = 'instruments';

    protected static ?string $title = 'Titoli e quotazioni';

    protected static ?string $navigationLabel = 'Titoli';

    protected static string|\UnitEnum|null $navigationGroup = 'Portafoglio';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    /** @var array<int, array{fingerprint: string, description: string}> */
    #[Locked]
    public array $quoteBases = [];

    protected function getViewData(): array
    {
        return ['instruments' => Instrument::query()->withCount('plans')->orderBy('ticker')->get()];
    }

    public function getHeading(): string
    {
        return 'Catalogo titoli';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('createInstrument')
            ->label('Nuovo titolo')->icon(Heroicon::Plus)
            ->modalHeading('Aggiungi un titolo')->modalSubmitActionLabel('Salva titolo')
            ->schema(self::instrumentFields())
            ->action(function (array $data): void {
                SaveInstrument::run(Auth::user(), $data);
                Notification::make()->title('Titolo aggiunto al catalogo')->success()->send();
            })];
    }

    public function editInstrumentAction(): Action
    {
        return Action::make('editInstrument')->label('Modifica titolo')->icon(Heroicon::PencilSquare)
            ->modalHeading('Modifica titolo')->modalSubmitActionLabel('Salva modifiche')
            ->fillForm(function (array $arguments): array {
                $instrument = Instrument::query()->findOrFail($arguments['instrument']);

                return ['ticker' => $instrument->ticker, 'name' => $instrument->name];
            })
            ->schema(self::instrumentFields(false))
            ->action(function (array $data, array $arguments): void {
                SaveInstrument::run(Auth::user(), $data, Instrument::query()->findOrFail($arguments['instrument']));
                Notification::make()->title('Titolo aggiornato')->success()->send();
            });
    }

    public function updateQuoteAction(): Action
    {
        return $this->quoteAction(false);
    }

    public function correctQuoteAction(): Action
    {
        return $this->quoteAction(true);
    }

    public function deleteInstrumentAction(): Action
    {
        return Action::make('deleteInstrument')->label('Elimina')->icon(Heroicon::Trash)->color('danger')
            ->requiresConfirmation()->modalHeading('Eliminare questo titolo?')
            ->modalDescription('Il titolo può essere eliminato soltanto se nessun PAC, anche chiuso, lo utilizza.')
            ->action(function (array $arguments): void {
                DeleteInstrument::run(Auth::user(), Instrument::query()->findOrFail($arguments['instrument']));
                Notification::make()->title('Titolo eliminato')->success()->send();
            });
    }

    /** @return array<TextInput|DatePicker> */
    public static function instrumentFields(bool $includeInitialQuote = true): array
    {
        $fields = [
            TextInput::make('ticker')->label('Ticker')->required()->maxLength(32)->placeholder('SWDA')
                ->helperText('Obbligatorio e univoco. Gli spazi esterni vengono rimossi e le lettere convertite in maiuscolo.'),
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
        ];

        if ($includeInitialQuote) {
            $fields[] = self::decimalField('initial_price', 'Prezzo iniziale (€, facoltativo)', 6);
            $fields[] = DatePicker::make('initial_price_date')->label('Data del prezzo iniziale')
                ->helperText('Prezzo e data sono facoltativi, ma devono essere compilati insieme.');
        }

        return $fields;
    }

    public static function decimalField(string $name, string $label, int $decimals = 2): TextInput
    {
        return TextInput::make($name)->label($label)->inputMode('decimal')->maxLength(32)
            ->placeholder($decimals === 6 ? '123,456789' : '750,00')
            ->dehydrateStateUsing(fn (mixed $state): ?string => $state === null || $state === '' ? null : str_replace(',', '.', trim((string) $state)));
    }

    private function quoteAction(bool $correction): Action
    {
        return Action::make($correction ? 'correctQuote' : 'updateQuote')
            ->label($correction ? 'Correggi prezzo' : 'Aggiorna prezzo')
            ->icon($correction ? Heroicon::Pencil : Heroicon::ArrowTrendingUp)
            ->color($correction ? 'warning' : 'primary')
            ->requiresConfirmation($correction)
            ->modalHeading($correction ? 'Correggi quotazione corrente' : 'Aggiorna la quotazione')
            ->modalDescription($correction
                ? 'Verifica i valori precedenti e quelli inseriti. Confermando puoi correggere anche una data errata con una precedente.'
                : 'La data deve essere uguale o successiva a quella corrente. Tutti i PAC sul titolo useranno la nuova quotazione.')
            ->modalSubmitActionLabel($correction ? 'Conferma correzione' : 'Aggiorna prezzo')
            ->fillForm(function (array $arguments) use ($correction): array {
                $instrument = Instrument::query()->findOrFail($arguments['instrument']);
                $this->captureQuote($instrument);

                return [
                    'unit_price' => $instrument->latest_price,
                    'price_date' => $correction ? ($instrument->latest_price_date?->toDateString() ?? now()->toDateString()) : now()->toDateString(),
                ];
            })
            ->schema(fn (array $arguments): array => [
                Text::make(fn (): string => 'Quotazione precedente: '.($this->quoteBases[(int) $arguments['instrument']]['description'] ?? 'non disponibile')),
                self::decimalField('unit_price', 'Prezzo unitario (€)', 6)->required(),
                DatePicker::make('price_date')->label('Data del prezzo')->required(),
            ])
            ->action(function (array $data, array $arguments, Action $action) use ($correction): void {
                $instrument = Instrument::query()->findOrFail($arguments['instrument']);

                try {
                    UpdateQuote::run(Auth::user(), $instrument, $data, $this->quoteBases[$instrument->id]['fingerprint'] ?? '', $correction);
                } catch (ValidationException $exception) {
                    if (isset($exception->errors()['expected_fingerprint'])) {
                        $this->captureQuote($instrument->fresh());
                        Notification::make()->title('Quotazione cambiata')->body('I tuoi valori sono conservati. Verifica la nuova quotazione precedente e conferma nuovamente.')->warning()->persistent()->send();
                        $action->halt();
                    }

                    Notification::make()->title('Controlla i dati')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();
                    throw $exception;
                }

                Notification::make()->title($correction ? 'Quotazione corretta' : 'Quotazione aggiornata')->success()->send();
            });
    }

    private function captureQuote(Instrument $instrument): void
    {
        $this->quoteBases[$instrument->id] = [
            'fingerprint' => UpdateQuote::fingerprint($instrument),
            'description' => $instrument->latest_price === null ? 'non disponibile'
                : $instrument->latest_price.' € del '.$instrument->latest_price_date->format('d/m/Y'),
        ];
    }
}
