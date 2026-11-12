<?php

use App\Actions\CreateSuccessor;
use App\Actions\DeleteInstrument;
use App\Actions\DeleteInvestment;
use App\Actions\DeletePlan;
use App\Actions\OpenPlan;
use App\Actions\PreviewInvestment;
use App\Actions\PreviewSuccessor;
use App\Actions\SaveInstrument;
use App\Actions\SaveInvestment;
use App\Actions\UpdatePlanConfiguration;
use App\Actions\UpdateQuote;
use App\Models\Instrument;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function vaUser(): User
{
    return User::factory()->create();
}

function vaInstrument(): Instrument
{
    return Instrument::factory()->create(['ticker' => fake()->unique()->bothify('???')]);
}

function vaPlan(User $user, ?Instrument $instrument = null, array $attributes = []): Plan
{
    return Plan::factory()->for($user)->for($instrument ?? vaInstrument())->create($attributes);
}

it('normalizes and saves a catalog instrument with an optional initial quote', function () {
    $instrument = app(SaveInstrument::class)->handle(vaUser(), [
        'ticker' => '  swda  ',
        'name' => 'Global Equity',
        'initial_price' => '102.123456',
        'initial_price_date' => '2026-11-10',
    ]);

    expect($instrument->ticker)->toBe('SWDA')
        ->and($instrument->latest_price)->toBe('102.123456')
        ->and($instrument->getRawOriginal('latest_price'))->toBe(102123456)
        ->and($instrument->latest_price_date->toDateString())->toBe('2026-11-10');
});

it('rejects duplicate tickers without overwriting the existing instrument', function () {
    $user = vaUser();
    $instrument = app(SaveInstrument::class)->handle($user, ['ticker' => 'SWDA', 'name' => 'Original']);

    expect(fn () => app(SaveInstrument::class)->handle($user, ['ticker' => ' swda ', 'name' => 'Replacement']))
        ->toThrow(ValidationException::class);
    expect($instrument->fresh()->name)->toBe('Original');
});

it('does not allow deletion of an instrument associated with any plan', function () {
    $user = vaUser();
    $instrument = vaInstrument();
    vaPlan($user, $instrument);

    expect(fn () => app(DeleteInstrument::class)->handle($user, $instrument))->toThrow(ValidationException::class);
    expect(Instrument::query()->find($instrument->id))->not->toBeNull();
});

it('opens zero and existing balance plans with the calendar target', function () {
    $user = vaUser();
    $instrument = vaInstrument();
    $action = app(OpenPlan::class);
    $zero = $action->handle($user, [
        'instrument_id' => $instrument->id,
        'monthly_increment' => '750.00',
        'start_period' => '2026-01',
        'opening_mode' => 'zero',
    ]);
    $existing = $action->handle($user, [
        'instrument_id' => $instrument->id,
        'monthly_increment' => '750.00',
        'start_period' => '2026-01',
        'opening_mode' => 'existing',
        'opening_period' => '2026-10',
        'opening_quantity' => '3',
        'opening_invested_amount' => '250.00',
        'opening_virtual_liquidity' => '20.00',
    ]);

    expect($zero->user_id)->toBe($user->id)
        ->and($zero->getRawOriginal('opening_target_amount'))->toBe(0)
        ->and($zero->firstRecordablePeriod())->toBe('2026-01-01')
        ->and($existing->getRawOriginal('opening_target_amount'))->toBe(750000)
        ->and($existing->targetFor('2026-11-01'))->toBe(825000)
        ->and($existing->firstRecordablePeriod())->toBe('2026-11-01');
});

it('previews and saves a confirmed investment snapshot and locks the plan configuration', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = [
        'period' => '2026-01',
        'unit_price' => '100.00',
        'price_date' => '2026-01-20',
    ];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);
    $investment = app(SaveInvestment::class)->handle($user, $plan, [...$input, 'purchased_quantity' => '8'], $preview['fingerprint']);

    expect($preview['snapshot']['proposed_quantity'])->toBe(8)
        ->and($investment->getRawOriginal('proposed_amount'))->toBe(80000)
        ->and($investment->getRawOriginal('purchased_amount'))->toBe(80000)
        ->and($investment->getRawOriginal('quantity_after'))->toBe(8)
        ->and($investment->getRawOriginal('invested_amount_after'))->toBe(80000)
        ->and($plan->fresh()->configuration_locked_at)->not->toBeNull()
        ->and($plan->instrument->fresh()->latest_price)->toBe('100');
});

it('rejects a save when inputs differ from the confirmed preview', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);

    expect(fn () => app(SaveInvestment::class)->handle(
        $user,
        $plan,
        [...$input, 'unit_price' => '101', 'purchased_quantity' => '8'],
        $preview['fingerprint'],
    ))->toThrow(ValidationException::class);
    expect(Investment::query()->count())->toBe(0);
});

it('rejects a stale investment preview after the plan state changes', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);
    $plan->configuration_locked_at = now();
    $plan->save();

    expect(fn () => app(SaveInvestment::class)->handle($user, $plan, [...$input, 'purchased_quantity' => '8'], $preview['fingerprint']))
        ->toThrow(ValidationException::class);
    expect(Investment::query()->count())->toBe(0);
});

it('rebuilds the last investment from its prior balance when corrected', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $firstInput = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $firstPreview = app(PreviewInvestment::class)->handle($user, $plan, $firstInput);
    $first = app(SaveInvestment::class)->handle($user, $plan, [...$firstInput, 'purchased_quantity' => '8'], $firstPreview['fingerprint']);
    $secondInput = ['period' => '2026-02', 'unit_price' => '100', 'price_date' => '2026-02-20'];
    $secondPreview = app(PreviewInvestment::class)->handle($user, $plan, [...$secondInput, 'purchased_quantity' => '0']);
    $second = app(SaveInvestment::class)->handle($user, $plan, [...$secondInput, 'purchased_quantity' => '0'], $secondPreview['fingerprint']);
    $correctionInput = ['unit_price' => '80', 'purchased_quantity' => '10'];
    $correctionPreview = app(PreviewInvestment::class)->handle($user, $plan, $correctionInput, $second);
    $corrected = app(SaveInvestment::class)->handle($user, $plan, $correctionInput, $correctionPreview['fingerprint'], $second);

    expect($corrected->period->toDateString())->toBe('2026-02-01')
        ->and($corrected->price_date->toDateString())->toBe('2026-02-20')
        ->and($corrected->getRawOriginal('quantity_before'))->toBe(8)
        ->and($corrected->getRawOriginal('quantity_after'))->toBe(18)
        ->and($first->fresh()->getRawOriginal('quantity_after'))->toBe(8);
});

it('forbids a user from previewing another user’s plan', function () {
    $plan = vaPlan(vaUser());

    expect(fn () => app(PreviewInvestment::class)->handle(vaUser(), $plan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20',
    ]))->toThrow(AuthorizationException::class);
});

it('updates quotes only with a current fingerprint and rejects older dates', function () {
    $instrument = vaInstrument();
    $user = vaUser();
    $fingerprint = UpdateQuote::fingerprint($instrument);
    $updated = app(UpdateQuote::class)->handle($user, $instrument, ['unit_price' => '110.25', 'price_date' => '2026-11-11'], $fingerprint);

    expect($updated->latest_price)->toBe('110.25');
    expect(fn () => app(UpdateQuote::class)->handle($user, $updated, ['unit_price' => '99', 'price_date' => '2026-11-10'], UpdateQuote::fingerprint($updated)))
        ->toThrow(ValidationException::class);
});

it('rejects a quote update when the current quote changed after the form opened', function () {
    $instrument = vaInstrument();
    $user = vaUser();
    $fingerprint = UpdateQuote::fingerprint($instrument);
    app(UpdateQuote::class)->handle($user, $instrument, ['unit_price' => '110', 'price_date' => '2026-11-11'], $fingerprint);

    expect(fn () => app(UpdateQuote::class)->handle($user, $instrument, ['unit_price' => '120', 'price_date' => '2026-11-12'], $fingerprint))
        ->toThrow(ValidationException::class);
    expect($instrument->fresh()->latest_price)->toBe('110');
});

it('allows explicit quote correction to replace a mistaken future date', function () {
    $instrument = vaInstrument();
    $instrument->latest_price_date = '2027-01-01';
    $instrument->save();

    $corrected = app(UpdateQuote::class)->handle(vaUser(), $instrument, [
        'unit_price' => '101.123456', 'price_date' => '2026-11-10',
    ], UpdateQuote::fingerprint($instrument), correction: true);

    expect($corrected->latest_price)->toBe('101.123456')
        ->and($corrected->latest_price_date->toDateString())->toBe('2026-11-10');
});

it('updates plan configuration only while it is editable and keeps its increment fixed', function () {
    $user = vaUser();
    $plan = vaPlan($user);
    $fingerprint = $plan->stateFingerprint();
    $updated = app(UpdatePlanConfiguration::class)->handle($user, $plan, [
        'instrument_id' => vaInstrument()->id,
        'start_period' => '2026-02',
        'opening_mode' => 'zero',
    ], $fingerprint);

    expect($updated->start_period->toDateString())->toBe('2026-02-01')
        ->and($updated->getRawOriginal('monthly_increment'))->toBe(75000);

    $lockedPlan = vaPlan($user, attributes: ['configuration_locked_at' => now()]);
    expect(fn () => app(UpdatePlanConfiguration::class)->handle($user, $lockedPlan, [
        'instrument_id' => $lockedPlan->instrument_id,
        'start_period' => '2026-02',
        'opening_mode' => 'zero',
    ], $lockedPlan->stateFingerprint()))->toThrow(ValidationException::class);
});

it('deletes only a standalone empty plan', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['configuration_locked_at' => now()]);

    app(DeletePlan::class)->handle($user, $plan, $plan->stateFingerprint());

    expect(Plan::query()->find($plan->id))->toBeNull();
});

it('does not delete a plan after it has a saved investment', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);
    app(SaveInvestment::class)->handle($user, $plan, [...$input, 'purchased_quantity' => '8'], $preview['fingerprint']);

    expect(fn () => app(DeletePlan::class)->handle($user, $plan, $plan->fresh()->stateFingerprint()))->toThrow(ValidationException::class);
    expect(Plan::query()->find($plan->id))->not->toBeNull();
});

it('closes a predecessor and creates one immediate successor with transferred balances', function () {
    $this->travelTo('2026-11-12 10:00:00');
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $initialInput = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $initialInput);
    $investment = app(SaveInvestment::class)->handle($user, $plan, [...$initialInput, 'purchased_quantity' => '8'], $preview['fingerprint']);
    $successorInput = ['monthly_increment' => '900', 'start_period' => '2026-11'];
    $successorPreview = app(PreviewSuccessor::class)->handle($user, $plan, $successorInput);
    $successor = app(CreateSuccessor::class)->handle($user, $plan, $successorInput, $successorPreview['fingerprint']);

    expect($plan->fresh()->status)->toBe('closed')
        ->and($plan->fresh()->closed_period->toDateString())->toBe('2026-10-01')
        ->and($successor->previous_plan_id)->toBe($plan->id)
        ->and($successor->getRawOriginal('opening_quantity'))->toBe(8)
        ->and($successor->getRawOriginal('opening_invested_amount'))->toBe(80000)
        ->and($successor->getRawOriginal('opening_target_amount'))->toBe(750000)
        ->and($successor->targetFor('2026-11-01'))->toBe(840000)
        ->and($investment->fresh())->not->toBeNull();
});

it('rejects a successor with a future start period without closing the predecessor', function () {
    $this->travelTo('2026-11-12 10:00:00');
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['monthly_increment' => '900', 'start_period' => '2026-12'];

    expect(fn () => app(PreviewSuccessor::class)->handle($user, $plan, $input))->toThrow(ValidationException::class);
    expect($plan->fresh()->status)->toBe('active');
});

it('rejects a second successor for a predecessor', function () {
    $this->travelTo('2026-11-12 10:00:00');
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['monthly_increment' => '900', 'start_period' => '2026-11'];
    $preview = app(PreviewSuccessor::class)->handle($user, $plan, $input);
    app(CreateSuccessor::class)->handle($user, $plan, $input, $preview['fingerprint']);

    expect(fn () => app(PreviewSuccessor::class)->handle($user, $plan, $input))->toThrow(ValidationException::class);
    expect(Plan::query()->count())->toBe(2);
});

it('deletes a selected latest investment only after its previewed state still matches', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);
    $investment = app(SaveInvestment::class)->handle($user, $plan, [...$input, 'purchased_quantity' => '8'], $preview['fingerprint']);

    app(DeleteInvestment::class)->handle($user, $investment, DeleteInvestment::fingerprint($plan->fresh(), $investment->fresh()));

    expect(Investment::query()->count())->toBe(0)
        ->and($plan->fresh()->configuration_locked_at)->not->toBeNull();
});

it('replaces virtual liquidity on correction and leaves the shared quote unchanged', function () {
    $user = vaUser();
    $instrument = vaInstrument();
    $plan = app(OpenPlan::class)->handle($user, [
        'instrument_id' => $instrument->id,
        'monthly_increment' => '50',
        'start_period' => '2025-12',
        'opening_mode' => 'existing',
        'opening_period' => '2025-12',
        'opening_quantity' => '7',
        'opening_invested_amount' => '0',
        'opening_virtual_liquidity' => '0',
    ]);
    $input = ['period' => '2026-01', 'unit_price' => '57.142857', 'price_date' => '2026-01-20', 'purchased_quantity' => '0'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);
    $investment = app(SaveInvestment::class)->handle($user, $plan, $input, $preview['fingerprint']);
    $quoteAfterOriginalSave = $instrument->fresh()->latest_price;
    $correctionInput = ['unit_price' => '50', 'purchased_quantity' => '0'];
    $correctionPreview = app(PreviewInvestment::class)->handle($user, $plan, $correctionInput, $investment);
    $corrected = app(SaveInvestment::class)->handle($user, $plan, $correctionInput, $correctionPreview['fingerprint'], $investment);

    expect($investment->getRawOriginal('theoretical_investment'))->toBe(-30000)
        ->and($corrected->getRawOriginal('theoretical_investment'))->toBe(-25000)
        ->and($corrected->getRawOriginal('virtual_liquidity_increase'))->toBe(25000)
        ->and($corrected->getRawOriginal('virtual_liquidity_after'))->toBe(25000)
        ->and($corrected->getRawOriginal('quantity_after'))->toBe(7)
        ->and($instrument->fresh()->latest_price)->toBe($quoteAfterOriginalSave);
});

it('rejects deletion when the selected investment changed after confirmation', function () {
    $user = vaUser();
    $plan = vaPlan($user, attributes: ['start_period' => '2026-01-01']);
    $input = ['period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20'];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);
    $investment = app(SaveInvestment::class)->handle($user, $plan, [...$input, 'purchased_quantity' => '8'], $preview['fingerprint']);
    $fingerprint = DeleteInvestment::fingerprint($plan->fresh(), $investment->fresh());
    $investment->quantity_after = 9;
    $investment->save();

    expect(fn () => app(DeleteInvestment::class)->handle($user, $investment->fresh(), $fingerprint))->toThrow(ValidationException::class);
    expect(Investment::query()->count())->toBe(1);
});
