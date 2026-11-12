<?php

namespace App\Actions\Concerns;

use App\Data\SuccessorData;
use App\Models\Plan;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

trait SuccessorState
{
    /** @param array<string, mixed> $input
     * @return array{monthly_increment: int, start_period: string, closed_period: string, opening_quantity: int, opening_invested_amount: int, opening_target_amount: int, opening_virtual_liquidity: int}
     */
    private function successorValues(Plan $plan, array $input): array
    {
        $data = $this->validate($input, [
            'monthly_increment' => ['required', 'string'],
            'start_period' => ['required', 'string'],
        ], [
            'monthly_increment.required' => 'L’incremento mensile è obbligatorio.',
            'start_period.required' => 'La decorrenza è obbligatoria.',
        ]);
        $monthlyIncrement = $this->money($data['monthly_increment'], 'monthly_increment', true);
        $startPeriod = $this->period($data['start_period'], 'start_period');
        $latest = $this->latestInvestment($plan);
        $lowerBound = max(
            (string) $plan->start_period->toDateString(),
            $plan->opening_period?->toDateString() ?? '',
            $latest?->period->toDateString() ?? '',
        );

        if ($startPeriod <= $lowerBound) {
            throw ValidationException::withMessages(['start_period' => 'La decorrenza deve seguire l’inizio, il saldo di apertura e l’ultima rilevazione del PAC.']);
        }

        if ($startPeriod > CarbonImmutable::now()->startOfMonth()->toDateString()) {
            throw ValidationException::withMessages(['start_period' => 'La decorrenza non può essere futura.']);
        }

        $closedPeriod = CarbonImmutable::parse($startPeriod)->subMonth()->startOfMonth()->toDateString();
        [$quantity, $investedAmount, $virtualLiquidity] = $this->positionBefore($plan);

        try {
            $targetAmount = $plan->targetFor($closedPeriod);
            Decimal::add($targetAmount, $monthlyIncrement);
        } catch (\OverflowException) {
            throw ValidationException::withMessages(['start_period' => 'L’obiettivo maturato supera i limiti numerici supportati.']);
        }

        try {
            Decimal::add($targetAmount, $monthlyIncrement);
        } catch (\OverflowException) {
            throw ValidationException::withMessages(['monthly_increment' => 'L’obiettivo del nuovo PAC supera i limiti numerici supportati.']);
        }

        return (new SuccessorData(
            $monthlyIncrement,
            $startPeriod,
            $closedPeriod,
            $quantity,
            $investedAmount,
            $targetAmount,
            $virtualLiquidity,
        ))->values();
    }

    /** @param array<string, int|string> $values */
    private function successorFingerprint(Plan $plan, array $values): string
    {
        $latest = $this->latestInvestment($plan);

        return hash('sha256', json_encode([
            'plan' => $plan->stateFingerprint(),
            'latest' => $latest?->getRawOriginal(),
            'values' => $values,
        ], JSON_THROW_ON_ERROR));
    }
}
