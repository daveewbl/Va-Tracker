<?php

namespace App\Data;

use Spatie\LaravelData\Data;

final class SuccessorData extends Data
{
    public function __construct(
        public int $monthlyIncrement,
        public string $startPeriod,
        public string $closedPeriod,
        public int $openingQuantity,
        public int $openingInvestedAmount,
        public int $openingTargetAmount,
        public int $openingVirtualLiquidity,
    ) {}

    /** @return array{monthly_increment: int, start_period: string, closed_period: string, opening_quantity: int, opening_invested_amount: int, opening_target_amount: int, opening_virtual_liquidity: int} */
    public function values(): array
    {
        return [
            'monthly_increment' => $this->monthlyIncrement,
            'start_period' => $this->startPeriod,
            'closed_period' => $this->closedPeriod,
            'opening_quantity' => $this->openingQuantity,
            'opening_invested_amount' => $this->openingInvestedAmount,
            'opening_target_amount' => $this->openingTargetAmount,
            'opening_virtual_liquidity' => $this->openingVirtualLiquidity,
        ];
    }
}
