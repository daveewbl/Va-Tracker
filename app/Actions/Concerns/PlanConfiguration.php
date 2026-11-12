<?php

namespace App\Actions\Concerns;

use App\Models\Instrument;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

trait PlanConfiguration
{
    /** @param array<string, mixed> $input
     * @return array<string, int|string>
     */
    private function configurationValues(array $input, ?int $fixedIncrement = null): array
    {
        $data = $this->validate($input, [
            'instrument_id' => ['required', 'integer', 'min:1'],
            'start_period' => ['required', 'string'],
            'opening_mode' => ['required', 'in:zero,existing'],
            'opening_period' => ['nullable', 'string'],
            'opening_quantity' => ['nullable'],
            'opening_invested_amount' => ['nullable', 'string'],
            'opening_virtual_liquidity' => ['nullable', 'string'],
            'monthly_increment' => [$fixedIncrement === null ? 'required' : 'nullable', 'string'],
        ], [
            'instrument_id.required' => 'Seleziona un titolo.',
            'opening_mode.in' => 'Seleziona una modalità di apertura valida.',
            'monthly_increment.required' => 'L’incremento mensile è obbligatorio.',
        ]);

        $instrumentId = (int) $data['instrument_id'];

        if (! Instrument::query()->whereKey($instrumentId)->exists()) {
            throw ValidationException::withMessages(['instrument_id' => 'Il titolo selezionato non è disponibile.']);
        }

        $start = $this->period($data['start_period'], 'start_period');
        $increment = $fixedIncrement ?? $this->money((string) $data['monthly_increment'], 'monthly_increment', true);

        if ($fixedIncrement !== null && isset($data['monthly_increment']) && $data['monthly_increment'] !== '') {
            if ($this->money((string) $data['monthly_increment'], 'monthly_increment', true) !== $fixedIncrement) {
                throw ValidationException::withMessages(['monthly_increment' => 'L’incremento mensile resta fisso per tutta la durata del PAC.']);
            }
        }

        $values = [
            'instrument_id' => $instrumentId,
            'monthly_increment' => $increment,
            'start_period' => $start,
            'opening_quantity' => 0,
            'opening_invested_amount' => 0,
            'opening_target_amount' => 0,
            'opening_virtual_liquidity' => 0,
            'opening_period' => '',
        ];

        if ($data['opening_mode'] === 'existing') {
            if (empty($data['opening_period'])) {
                throw ValidationException::withMessages(['opening_period' => 'Indica l’ultimo mese coperto dal saldo iniziale.']);
            }

            $openingPeriod = $this->period((string) $data['opening_period'], 'opening_period');

            if ($openingPeriod < $start) {
                throw ValidationException::withMessages(['opening_period' => 'Il saldo iniziale non può precedere l’inizio originario del PAC.']);
            }

            if (! array_key_exists('opening_quantity', $data) || $data['opening_quantity'] === '' || $data['opening_quantity'] === null) {
                throw ValidationException::withMessages(['opening_quantity' => 'Indica le quote possedute alla data di apertura.']);
            }

            $monthCount = ((int) substr($openingPeriod, 0, 4) - (int) substr($start, 0, 4)) * 12
                + (int) substr($openingPeriod, 5, 2) - (int) substr($start, 5, 2) + 1;

            try {
                $target = Decimal::multiply($increment, $monthCount);
            } catch (\OverflowException) {
                throw ValidationException::withMessages(['opening_period' => 'Il periodo produce un obiettivo superiore ai limiti supportati.']);
            }

            $values['opening_period'] = $openingPeriod;
            $values['opening_quantity'] = $this->quantity($data['opening_quantity'], 'opening_quantity');
            $values['opening_invested_amount'] = $this->money((string) ($data['opening_invested_amount'] ?? ''), 'opening_invested_amount');
            $values['opening_target_amount'] = $target;
            $values['opening_virtual_liquidity'] = $this->money((string) ($data['opening_virtual_liquidity'] ?? ''), 'opening_virtual_liquidity');
        } elseif ($data['opening_mode'] === 'zero') {
            foreach (['opening_quantity', 'opening_invested_amount', 'opening_virtual_liquidity'] as $field) {
                if (isset($input[$field]) && $input[$field] !== '' && $input[$field] !== '0' && $input[$field] !== 0 && $input[$field] !== '0.00') {
                    throw ValidationException::withMessages([$field => 'Con apertura da zero i saldi iniziali devono essere vuoti o pari a zero.']);
                }
            }
        }

        try {
            Decimal::add($values['opening_target_amount'], $increment);
        } catch (\OverflowException) {
            throw ValidationException::withMessages(['monthly_increment' => 'L’obiettivo del primo periodo supera i limiti numerici supportati.']);
        }

        return $values;
    }
}
