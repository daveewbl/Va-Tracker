<?php

use App\Actions\UpdateQuote;
use App\Filament\Pages\Instruments;
use App\Filament\Pages\PlanDetail;
use App\Filament\Pages\Plans;
use App\Models\Instrument;
use App\Models\Plan;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    config(['app.env' => 'local']);
});

it('creates and edits a ticker while preserving the initial six decimal quote', function () {
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test(Instruments::class)
        ->callAction('createInstrument', data: ['ticker' => ' swda ', 'name' => 'World', 'initial_price' => '123,456789', 'initial_price_date' => '2026-11-10'])
        ->assertHasNoFormErrors();
    $instrument = Instrument::query()->sole();

    $component->callAction(TestAction::make('editInstrument')->arguments(['instrument' => $instrument->id]), data: ['ticker' => 'SWDA', 'name' => 'Nuovo nome'])
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('instruments', ['id' => $instrument->id, 'ticker' => 'SWDA', 'name' => 'Nuovo nome', 'latest_price' => 123456789]);
});

it('recovers a changed quote without overwriting it on the first confirmation', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create(['latest_price' => '100', 'latest_price_date' => '2026-11-10']);
    $component = Livewire::actingAs($user)->test(Instruments::class)
        ->mountAction(TestAction::make('updateQuote')->arguments(['instrument' => $instrument->id]))
        ->fillForm(['unit_price' => '110,123456', 'price_date' => '2026-11-12']);
    UpdateQuote::run($user, $instrument, ['unit_price' => '105', 'price_date' => '2026-11-11'], UpdateQuote::fingerprint($instrument));

    $component->callMountedAction()->assertHasNoFormErrors();
    $this->assertDatabaseHas('instruments', ['id' => $instrument->id, 'latest_price' => 105000000]);

    $component->callMountedAction()->assertHasNoFormErrors();
    $this->assertDatabaseHas('instruments', ['id' => $instrument->id, 'latest_price' => 110123456, 'latest_price_date' => '2026-11-12']);
});

it('opens an existing PAC only after reviewing its derived first target', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();
    $component = Livewire::actingAs($user)->test(Plans::class)
        ->callAction('openPlan', data: [
            'instrument_id' => $instrument->id, 'monthly_increment' => '750,00', 'start_period' => '2026-01',
            'opening_mode' => 'existing', 'opening_period' => '2026-10', 'opening_quantity' => '40',
            'opening_invested_amount' => '6000,00', 'opening_virtual_liquidity' => '100,00',
        ])->assertHasNoFormErrors()->assertSee('8250.00');
    $this->assertDatabaseCount('plans', 0);

    $component->callAction('confirmPlan')->assertHasNoFormErrors();

    $plan = Plan::query()->sole();
    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'monthly_increment' => 75000, 'opening_target_amount' => 750000]);
    $this->assertDatabaseCount('investments', 0);
    $component->assertRedirect(PlanDetail::getUrl(['plan' => $plan->id]));
});

it('keeps the fixed increment during configuration review and rejects stale deletion', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->for($user)->create();
    $component = Livewire::actingAs($user)->test(Plans::class)
        ->callAction(TestAction::make('editConfiguration')->arguments(['plan' => $plan->id]), data: [
            'instrument_id' => $plan->instrument_id, 'start_period' => '2026-02', 'opening_mode' => 'zero',
        ])->assertHasNoFormErrors()
        ->callAction('confirmPlan')->assertHasNoFormErrors();
    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'monthly_increment' => 75000, 'start_period' => '2026-02-01']);

    $component = Livewire::actingAs($user)->test(Plans::class)
        ->mountAction(TestAction::make('deletePlan')->arguments(['plan' => $plan->id]));
    $plan->fresh()->update(['start_period' => '2026-03-01']);
    $component->callMountedAction()->assertHasErrors(['expected_fingerprint']);
    $this->assertModelExists($plan);
});

it('lists only the authenticated users plans', function () {
    $user = User::factory()->create();
    $foreignInstrument = Instrument::factory()->create(['name' => 'Piano riservato di un altro utente']);
    Plan::factory()->for($foreignInstrument)->create();

    Livewire::actingAs($user)->test(Plans::class)->assertDontSee('Piano riservato di un altro utente');
});
