<?php

namespace App\Services;

use App\Support\Decimal;
use InvalidArgumentException;

class ValueAveragingCalculator
{
    /**
     * Calculate the proposed and confirmed monthly snapshot using exact integer arithmetic.
     *
     * @return array{
     *     quantity_before: int,
     *     target_amount: int,
     *     portfolio_value_before: int,
     *     theoretical_investment: int,
     *     proposed_quantity: int,
     *     proposed_amount: int,
     *     purchased_quantity: int,
     *     purchased_amount: int,
     *     quantity_after: int,
     *     invested_amount_after: int,
     *     virtual_liquidity_increase: int,
     *     virtual_liquidity_after: int,
     *     range_status: string,
     *     rounding_exceeds_increment: bool
     * }
     */
    public function calculate(
        int $quantityBefore,
        int $targetAmount,
        int $monthlyIncrement,
        int $priceMicros,
        int $purchasedQuantity,
        int $investedAmountBefore,
        int $virtualLiquidityBefore,
    ): array {
        if ($quantityBefore < 0 || $targetAmount < 0 || $monthlyIncrement <= 0 || $priceMicros <= 0
            || $purchasedQuantity < 0 || $investedAmountBefore < 0 || $virtualLiquidityBefore < 0) {
            throw new InvalidArgumentException('Calculation inputs must follow the non-negative domain rules.');
        }

        $portfolioMicros = Decimal::multiply($quantityBefore, $priceMicros);
        $targetMicros = Decimal::multiply($targetAmount, 10_000);
        $incrementMicros = Decimal::multiply($monthlyIncrement, 10_000);
        $theoreticalMicros = $targetMicros - $portfolioMicros;
        $portfolioValueBefore = Decimal::roundMoney($portfolioMicros);
        $theoreticalInvestment = Decimal::roundMoney($theoreticalMicros);
        $proposedQuantity = $theoreticalMicros <= 0
            ? 0
            : $this->nearestWholeUnits($theoreticalMicros, $priceMicros);
        $proposedAmountMicros = Decimal::multiply($proposedQuantity, $priceMicros);
        $purchasedAmountMicros = Decimal::multiply($purchasedQuantity, $priceMicros);
        $liquidityIncrease = $theoreticalMicros < 0 ? Decimal::roundMoney(-$theoreticalMicros) : 0;
        $rangeStatus = match (true) {
            $theoreticalMicros < 0 => 'virtual_liquidity',
            $theoreticalMicros <= $incrementMicros => 'in_range',
            default => 'above_increment',
        };

        return [
            'quantity_before' => $quantityBefore,
            'target_amount' => $targetAmount,
            'portfolio_value_before' => $portfolioValueBefore,
            'theoretical_investment' => $theoreticalInvestment,
            'proposed_quantity' => $proposedQuantity,
            'proposed_amount' => Decimal::roundMoney($proposedAmountMicros),
            'purchased_quantity' => $purchasedQuantity,
            'purchased_amount' => Decimal::roundMoney($purchasedAmountMicros),
            'quantity_after' => Decimal::add($quantityBefore, $purchasedQuantity),
            'invested_amount_after' => Decimal::add($investedAmountBefore, Decimal::roundMoney($purchasedAmountMicros)),
            'virtual_liquidity_increase' => $liquidityIncrease,
            'virtual_liquidity_after' => Decimal::add($virtualLiquidityBefore, $liquidityIncrease),
            'range_status' => $rangeStatus,
            'rounding_exceeds_increment' => $theoreticalMicros >= 0
                && $theoreticalMicros <= $incrementMicros
                && $proposedAmountMicros > $incrementMicros,
        ];
    }

    private function nearestWholeUnits(int $amountMicros, int $priceMicros): int
    {
        $wholeUnits = intdiv($amountMicros, $priceMicros);
        $remainder = $amountMicros % $priceMicros;
        $halfThreshold = intdiv($priceMicros, 2) + ($priceMicros % 2);

        if ($remainder >= $halfThreshold) {
            return Decimal::add($wholeUnits, 1);
        }

        return $wholeUnits;
    }
}
