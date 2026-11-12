<?php

namespace App\Actions;

use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Instrument;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveInstrument
{
    use AsAction;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input, ?Instrument $instrument = null): Instrument
    {
        $ticker = strtoupper(trim((string) ($input['ticker'] ?? '')));
        $input['ticker'] = $ticker;
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'ticker' => [
                'required', 'string', 'max:32',
                Rule::unique('instruments', 'ticker')->ignore($instrument?->getKey()),
            ],
        ];

        if ($instrument === null) {
            $rules['initial_price'] = ['nullable', 'string'];
            $rules['initial_price_date'] = ['nullable', 'date_format:Y-m-d'];
        }

        $data = $this->validate($input, $rules, [
            'name.required' => 'Il nome del titolo è obbligatorio.',
            'ticker.required' => 'Il ticker è obbligatorio.',
            'ticker.unique' => 'Esiste già un titolo con questo ticker.',
        ]);

        if ($instrument !== null && array_key_exists('initial_price', $input)) {
            throw ValidationException::withMessages(['initial_price' => 'La quotazione si aggiorna con l’azione dedicata.']);
        }

        $price = null;
        $priceDate = null;

        if ($instrument === null) {
            $hasPrice = isset($input['initial_price']) && $input['initial_price'] !== '';
            $hasDate = isset($input['initial_price_date']) && $input['initial_price_date'] !== '';

            if ($hasPrice !== $hasDate) {
                throw ValidationException::withMessages([
                    $hasPrice ? 'initial_price_date' : 'initial_price' => 'Prezzo iniziale e data devono essere presenti insieme.',
                ]);
            }

            if ($hasPrice) {
                $price = $this->price((string) $input['initial_price'], 'initial_price');
                $priceDate = $this->date((string) $input['initial_price_date'], 'initial_price_date');
            }
        }

        return DB::transaction(function () use ($data, $instrument, $price, $priceDate): Instrument {
            if ($instrument !== null) {
                $instrument = Instrument::query()->findOrFail($instrument->getKey());
            }

            $tickerExists = Instrument::query()
                ->where('ticker', $data['ticker'])
                ->when($instrument, fn (Builder $query) => $query->whereKeyNot($instrument->getKey()))
                ->exists();

            if ($tickerExists) {
                throw ValidationException::withMessages(['ticker' => 'Esiste già un titolo con questo ticker.']);
            }

            $instrument ??= new Instrument;
            $instrument->name = $data['name'];
            $instrument->ticker = $data['ticker'];

            if ($instrument->exists === false) {
                $instrument->latest_price = $price === null ? null : Decimal::formatPrice($price);
                $instrument->latest_price_date = $priceDate;
            }

            $instrument->save();

            return $instrument->refresh();
        }, attempts: 5);
    }
}
