<?php

use App\Filament\Pages\Dashboard;
use App\Models\Instrument;
use App\Models\Plan;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->app['env'] = 'local';
    config(['app.env' => 'local']);
});

it('opens the personal dashboard with a first plan action', function () {
    $this->actingAs(User::factory()->create());

    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSee('Il tuo primo PAC parte da qui')
        ->assertSee('Liquidità virtuale');
});

it('values active holdings at the shared quote and keeps virtual liquidity separate', function () {
    $this->travelTo(now()->setDate(2026, 11, 12));
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['latest_price' => '100.500000', 'latest_price_date' => '2026-11-10']);
    Plan::factory()->for($user)->for($instrument)->create([
        'opening_period' => '2026-10-01', 'opening_quantity' => 23,
        'opening_invested_amount' => 230000, 'opening_virtual_liquidity' => 7000,
        'opening_target_amount' => 750000, 'start_period' => '2026-01-01',
    ]);
    Plan::factory()->for($user)->for($instrument)->create([
        'opening_period' => '2026-10-01', 'opening_quantity' => 2,
        'opening_invested_amount' => 19000, 'opening_virtual_liquidity' => 1000,
        'opening_target_amount' => 750000, 'start_period' => '2026-01-01',
    ]);
    Plan::factory()->for($user)->for($instrument)->create([
        'status' => 'closed', 'closed_period' => '2026-10-01',
        'opening_period' => '2026-09-01', 'opening_quantity' => 100,
        'opening_invested_amount' => 999999, 'opening_virtual_liquidity' => 999999,
        'start_period' => '2026-01-01',
    ]);
    Plan::factory()->for($instrument)->create(['opening_quantity' => 999]);
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertViewHas('totalInvested', 249000)
        ->assertViewHas('totalValue', 251250)
        ->assertViewHas('totalVirtual', 8000)
        ->assertViewHas('missingPrices', 0)
        ->assertSee('100,5');
});

it('marks totals as partial only for positive holdings without a quote', function () {
    $this->travelTo(now()->setDate(2026, 11, 12));
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['latest_price' => null, 'latest_price_date' => null]);
    Plan::factory()->for($user)->for($instrument)->create(['opening_quantity' => 5]);
    Plan::factory()->for($user)->for($instrument)->create(['opening_quantity' => 0]);
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertViewHas('totalValue', 0)
        ->assertViewHas('missingPrices', 1)
        ->assertSee('Valore parziale')
        ->assertSee('Non disponibile');
});

it('does not request a current month measurement before the opening balance is covered', function () {
    $this->travelTo(now()->setDate(2026, 11, 12));
    $user = User::factory()->create();
    Plan::factory()->for($user)->create([
        'start_period' => '2026-01-01', 'opening_period' => '2026-11-01',
        'opening_target_amount' => 825000,
    ]);
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee('Periodo già coperto')
        ->assertDontSee('Da rilevare')
        ->assertSee('8250,00');
});
