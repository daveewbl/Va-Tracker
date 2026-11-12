<?php

namespace Database\Factories;

use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticker' => fake()->unique()->bothify('???'),
            'name' => fake()->words(2, true),
            'latest_price' => '100.000000',
            'latest_price_date' => '2026-01-01',
        ];
    }
}
