<?php

namespace Database\Factories;

use App\Models\Instrument;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instrument_id' => Instrument::factory(),
            'monthly_increment' => 75000,
            'start_period' => '2026-01-01',
            'status' => 'active',
            'previous_plan_id' => null,
            'opening_period' => null,
            'opening_quantity' => 0,
            'opening_invested_amount' => 0,
            'opening_target_amount' => 0,
            'opening_virtual_liquidity' => 0,
            'closed_period' => null,
            'configuration_locked_at' => null,
        ];
    }
}
