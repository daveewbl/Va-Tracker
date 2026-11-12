<?php

use App\Actions\PreviewPlan;
use App\Models\Instrument;
use App\Models\User;

it('previews the calendar opening balance and first month without writing a plan', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();

    $preview = PreviewPlan::run($user, [
        'instrument_id' => $instrument->id,
        'monthly_increment' => '750.00',
        'start_period' => '2026-01',
        'opening_mode' => 'existing',
        'opening_period' => '2026-10',
        'opening_quantity' => '40',
        'opening_invested_amount' => '6000.00',
        'opening_virtual_liquidity' => '100.00',
    ]);

    expect($preview['opening_target_amount'])->toBe(750000)
        ->and($preview['first_target_amount'])->toBe(825000)
        ->and($preview['first_period'])->toBe('2026-11-01');
    $this->assertDatabaseCount('plans', 0);
    $this->assertDatabaseCount('investments', 0);
});
