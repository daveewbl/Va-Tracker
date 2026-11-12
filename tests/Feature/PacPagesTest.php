<?php

use App\Filament\Pages\Instruments;
use App\Filament\Pages\PlanDetail;
use App\Filament\Pages\Plans;
use App\Models\Instrument;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['app.env' => 'local']);
});

it('renders the instrument catalog and authenticated plans', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['ticker' => 'SWDA', 'name' => 'MSCI World']);
    $plan = Plan::factory()->for($user)->for($instrument)->create();

    $this->actingAs($user)
        ->get(Instruments::getUrl())
        ->assertOk()
        ->assertSee('Catalogo titoli')
        ->assertSee('SWDA');

    $this->actingAs($user)
        ->get(Plans::getUrl())
        ->assertOk()
        ->assertSee('I tuoi PAC')
        ->assertSee('MSCI World')
        ->assertSee(PlanDetail::getUrl(['plan' => $plan->getKey()]));
});

it('renders full historical snapshots without writing and hides another users plan', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $instrument = Instrument::factory()->create(['ticker' => 'SWDA', 'name' => 'MSCI World']);
    $plan = Plan::factory()->for($owner)->for($instrument)->create();
    Investment::factory()->for($plan)->create();

    $this->actingAs($owner)
        ->get(PlanDetail::getUrl(['plan' => $plan->getKey()]))
        ->assertOk()
        ->assertSee('Andamento per calendario')
        ->assertSee('Storico completo')
        ->assertSee('Fotografia');

    $this->assertDatabaseCount('investments', 1);

    $this->actingAs($otherUser)
        ->get(PlanDetail::getUrl(['plan' => $plan->getKey()]))
        ->assertNotFound();
});

it('previews a monthly record and saves the confirmed actual quantity', function () {
    $this->travelTo('2026-11-12 12:00:00');
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['ticker' => 'SWDA', 'name' => 'MSCI World']);
    $plan = Plan::factory()->for($user)->for($instrument)->create([
        'start_period' => '2026-10-01',
        'monthly_increment' => '750.00',
    ]);

    $component = Livewire::actingAs($user)
        ->test(PlanDetail::class, ['plan' => $plan->getKey()])
        ->callAction('previewInvestment', data: [
            'period' => '2026-11',
            'unit_price' => '100',
            'price_date' => '2026-11-12',
        ])
        ->assertHasNoFormErrors()
        ->assertSet('investmentSnapshot', fn (array $snapshot): bool => ($snapshot['proposed_quantity'] ?? null) === 15 && ($snapshot['purchased_quantity'] ?? null) === 15);

    $this->assertDatabaseCount('investments', 0);

    $component->callAction('adjustInvestment', data: ['purchased_quantity' => 14])
        ->assertSet('investmentSnapshot', fn (array $snapshot): bool => ($snapshot['purchased_quantity'] ?? null) === 14)
        ->callAction('saveInvestment');

    $this->assertDatabaseHas('investments', [
        'plan_id' => $plan->getKey(),
        'period' => '2026-11-01',
        'unit_price' => 100_000_000,
        'purchased_quantity' => 14,
        'quantity_after' => 14,
    ]);
    $this->assertDatabaseHas('instruments', [
        'id' => $instrument->getKey(),
        'latest_price' => 100_000_000,
        'latest_price_date' => '2026-11-12',
    ]);
});

it('previews and confirms an immediate successor on the same instrument', function () {
    $this->travelTo('2026-11-12 12:00:00');
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['ticker' => 'SWDA', 'name' => 'MSCI World']);
    $plan = Plan::factory()->for($user)->for($instrument)->create([
        'start_period' => '2026-01-01',
        'opening_quantity' => 12,
        'opening_invested_amount' => '1000.00',
        'opening_target_amount' => '7500.00',
        'opening_virtual_liquidity' => '25.00',
        'opening_period' => '2026-10-01',
    ]);

    Livewire::actingAs($user)
        ->test(PlanDetail::class, ['plan' => $plan->getKey()])
        ->callAction('previewSuccessor', data: [
            'monthly_increment' => '500',
            'start_period' => '2026-11',
        ])
        ->assertSet('successorPreview', fn (array $preview): bool => ($preview['closed_period'] ?? null) === '2026-10-01' && ($preview['opening_quantity'] ?? null) === 12)
        ->callAction('createSuccessor');

    $successor = Plan::query()->where('previous_plan_id', $plan->getKey())->firstOrFail();

    expect($plan->fresh()->status)->toBe('closed')
        ->and($plan->fresh()->closed_period->format('Y-m-d'))->toBe('2026-10-01')
        ->and($successor->instrument_id)->toBe($instrument->getKey())
        ->and($successor->opening_quantity)->toBe(12);
});

it('uses a closed PAC historical price and marks a future PAC as not started', function () {
    $this->travelTo('2026-11-12 12:00:00');
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create([
        'ticker' => 'SWDA',
        'name' => 'MSCI World',
        'latest_price' => '200.000000',
        'latest_price_date' => '2026-11-12',
    ]);
    $closedPlan = Plan::factory()->for($user)->for($instrument)->create([
        'status' => 'closed',
        'start_period' => '2026-01-01',
        'closed_period' => '2026-01-01',
    ]);
    Investment::factory()->for($closedPlan)->create([
        'period' => '2026-01-01',
        'unit_price' => '100.000000',
        'price_date' => '2026-01-12',
        'purchased_quantity' => 8,
        'quantity_after' => 8,
    ]);
    $futurePlan = Plan::factory()->for($user)->for($instrument)->create([
        'start_period' => '2026-12-01',
    ]);

    $this->actingAs($user)
        ->get(PlanDetail::getUrl(['plan' => $closedPlan->getKey()]))
        ->assertOk()
        ->assertSee('€ 100')
        ->assertSee('€ 800.00');

    $this->actingAs($user)
        ->get(PlanDetail::getUrl(['plan' => $futurePlan->getKey()]))
        ->assertOk()
        ->assertSee('Il piano non è ancora iniziato')
        ->assertSee('—');
});
