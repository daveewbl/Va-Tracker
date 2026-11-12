<?php

use App\Filament\Pages\PlanDetail;
use App\Models\Instrument;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    config(['app.env' => 'local']);
});

it('keeps historical chart gaps and snapshots when the current quote changes', function () {
    $this->travelTo('2026-03-12 12:00:00');
    $user = User::factory()->create();
    $plan = Plan::factory()->for($user)->create();
    Investment::factory()->for($plan)->create(['period' => '2026-01-01', 'portfolio_value_before' => 0]);
    Investment::factory()->for($plan)->create(['period' => '2026-03-01', 'portfolio_value_before' => 123456, 'target_amount' => 225000]);
    $component = Livewire::actingAs($user)->test(PlanDetail::class, ['plan' => $plan->id]);

    $points = $component->instance()->chartPoints();
    expect(array_column($points, 'target'))->toBe([75000, 150000, 225000])
        ->and(array_column($points, 'actual'))->toBe([0, null, 123456]);

    $plan->instrument->update(['latest_price' => '999.123456', 'latest_price_date' => '2026-03-12']);
    expect(array_column($component->instance()->chartPoints(), 'actual'))->toBe([0, null, 123456]);
});

it('shows the current target after the current month has already been recorded', function () {
    $this->travelTo('2026-11-12 12:00:00');
    $user = User::factory()->create();
    $plan = Plan::factory()->for($user)->create(['start_period' => '2026-11-01']);
    Investment::factory()->for($plan)->create(['period' => '2026-11-01']);

    Livewire::actingAs($user)->test(PlanDetail::class, ['plan' => $plan->id])
        ->assertViewHas('calendarTarget', 75000)
        ->assertViewHas('targetForPeriod', '2026-11-01');
});

it('confirms deletion of the last monthly snapshot and restores the opening position', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['latest_price' => '150', 'latest_price_date' => '2026-11-11']);
    $plan = Plan::factory()->for($user)->for($instrument)->create([
        'opening_period' => '2026-10-01', 'opening_quantity' => 20, 'opening_invested_amount' => 250000,
        'opening_target_amount' => 225000, 'opening_virtual_liquidity' => 10000, 'configuration_locked_at' => now(),
    ]);
    $investment = Investment::factory()->for($plan)->create([
        'period' => '2026-11-01', 'unit_price' => '160', 'quantity_before' => 20, 'quantity_after' => 20,
        'purchased_quantity' => 0, 'purchased_amount' => 0, 'invested_amount_after' => 250000,
        'virtual_liquidity_increase' => 20000, 'virtual_liquidity_after' => 30000,
    ]);

    Livewire::actingAs($user)->test(PlanDetail::class, ['plan' => $plan->id])
        ->callAction(TestAction::make('deleteLatestInvestment')->arguments(['investment' => $investment->id]))
        ->assertHasNoFormErrors();

    $this->assertModelMissing($investment);
    expect($plan->fresh()->quantity())->toBe(20)
        ->and($plan->fresh()->virtualLiquidity())->toBe(10000)
        ->and($plan->fresh()->configuration_locked_at)->not->toBeNull();
    $this->assertDatabaseHas('instruments', ['id' => $instrument->id, 'latest_price' => 150000000, 'latest_price_date' => '2026-11-11']);
});
