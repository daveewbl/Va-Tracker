<?php

namespace App\Actions;

use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Instrument;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateQuote
{
    use AsAction;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input */
    public function handle(User $user, Instrument $instrument, array $input, string $expectedFingerprint, bool $correction = false): Instrument
    {
        $data = $this->validate($input, [
            'unit_price' => ['required', 'string'],
            'price_date' => ['required', 'date_format:Y-m-d'],
        ], [
            'unit_price.required' => 'Il prezzo è obbligatorio.',
            'price_date.required' => 'La data del prezzo è obbligatoria.',
        ]);
        $price = $this->price($data['unit_price'], 'unit_price');
        $date = $this->date($data['price_date'], 'price_date');

        return DB::transaction(function () use ($instrument, $expectedFingerprint, $correction, $price, $date): Instrument {
            $current = Instrument::query()->find($instrument->getKey());

            if (! $current) {
                throw ValidationException::withMessages(['instrument' => 'Il titolo non è più disponibile.']);
            }

            if (! hash_equals($expectedFingerprint, self::fingerprint($current))) {
                $this->fingerprintMismatch();
            }

            $currentDate = $current->latest_price_date?->toDateString();

            if (! $correction && $currentDate !== null && $date < $currentDate) {
                throw ValidationException::withMessages(['price_date' => 'La data è precedente alla quotazione corrente. Usa la correzione esplicita per correggerla.']);
            }

            $current->latest_price = Decimal::formatPrice($price);
            $current->latest_price_date = $date;
            $current->save();

            return $current->refresh();
        }, attempts: 5);
    }

    public static function fingerprint(Instrument $instrument): string
    {
        return hash('sha256', json_encode([
            'id' => $instrument->getKey(),
            'price' => $instrument->getRawOriginal('latest_price'),
            'date' => $instrument->getRawOriginal('latest_price_date'),
        ], JSON_THROW_ON_ERROR));
    }
}
