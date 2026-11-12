<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'period' => '2026-01-01',
            'unit_price' => '100.000000',
            'price_date' => '2026-01-01',
            'quantity_before' => 0,
            'target_amount' => 75000,
            'portfolio_value_before' => 0,
            'theoretical_investment' => 75000,
            'proposed_quantity' => 8,
            'proposed_amount' => 80000,
            'purchased_quantity' => 8,
            'purchased_amount' => 80000,
            'quantity_after' => 8,
            'invested_amount_after' => 80000,
            'virtual_liquidity_increase' => 0,
            'virtual_liquidity_after' => 0,
            'range_status' => 'in_range',
            'rounding_exceeds_increment' => true,
        ];
    }
}
