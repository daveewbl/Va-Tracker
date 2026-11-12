<?php

namespace App\Actions\Concerns;

use App\Models\Plan;
use App\Services\ValueAveragingCalculator;
use Illuminate\Validation\ValidationException;

trait InvestmentCalculation
{
    /** @return array<string, int|string|bool> */
    private function calculateSnapshot(
        Plan $plan,
        string $period,
        int $priceMicros,
        int $purchasedQuantity,
        int $quantityBefore,
        int $investedAmountBefore,
        int $virtualLiquidityBefore,
    ): array {
        try {
            return (new ValueAveragingCalculator)->calculate(
                $quantityBefore,
                $plan->targetFor($period),
                (int) $plan->getRawOriginal('monthly_increment'),
                $priceMicros,
                $purchasedQuantity,
                $investedAmountBefore,
                $virtualLiquidityBefore,
            );
        } catch (\OverflowException $exception) {
            throw ValidationException::withMessages(['unit_price' => 'Questi valori superano i limiti numerici supportati.']);
        }
    }
}
