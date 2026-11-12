<?php

use App\Actions\CreateSuccessor;
use App\Actions\DeleteInvestment;
use App\Actions\DeletePlan;
use App\Actions\PreviewInvestment;
use App\Actions\PreviewSuccessor;
use App\Actions\SaveInvestment;
use App\Actions\UpdatePlanConfiguration;
use App\Models\Instrument;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function domainUser(): User
{
    return User::factory()->create();
}

function domainInstrument(): Instrument
{
    return Instrument::factory()->create(['ticker' => fake()->unique()->bothify('???')]);
}

function domainPlan(User $user, Instrument $instrument, array $attributes = []): Plan
{
    return Plan::factory()->for($user)->for($instrument)->create($attributes);
}

function saveDomainInvestment(User $user, Plan $plan, array $input): Investment
{
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $input);

    return app(SaveInvestment::class)->handle($user, $plan, $input, $preview['fingerprint']);
}

it('forbids another user from mutating or previewing a plan and its investment', function () {
    $owner = domainUser();
    $otherUser = domainUser();
    $instrument = domainInstrument();
    $plan = domainPlan($owner, $instrument);
    $investment = Investment::factory()->for($plan)->create();
    $planFingerprint = $plan->stateFingerprint();

    expect(fn () => app(UpdatePlanConfiguration::class)->handle($otherUser, $plan, [
        'instrument_id' => $instrument->id,
        'start_period' => '2026-02',
        'opening_mode' => 'zero',
    ], $planFingerprint))->toThrow(AuthorizationException::class);

    expect(fn () => app(DeletePlan::class)->handle($otherUser, $plan, $planFingerprint))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(PreviewInvestment::class)->handle($otherUser, $plan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20',
    ]))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveInvestment::class)->handle($otherUser, $plan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20', 'purchased_quantity' => '1',
    ], 'invalid'))->toThrow(AuthorizationException::class);
    expect(fn () => app(DeleteInvestment::class)->handle($otherUser, $investment, 'invalid'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(PreviewSuccessor::class)->handle($otherUser, $plan, [
        'monthly_increment' => '900', 'start_period' => '2026-11',
    ]))->toThrow(AuthorizationException::class);
    expect(fn () => app(CreateSuccessor::class)->handle($otherUser, $plan, [
        'monthly_increment' => '900', 'start_period' => '2026-11',
    ], 'invalid'))->toThrow(AuthorizationException::class);

    expect(Plan::query()->count())->toBe(1)
        ->and(Investment::query()->count())->toBe(1)
        ->and($plan->fresh()->status)->toBe('active');
});

it('keeps both previews read only', function () {
    $this->travelTo('2026-11-12 10:00:00');
    $user = domainUser();
    $instrument = domainInstrument();
    $plan = domainPlan($user, $instrument, ['start_period' => '2026-01-01']);
    $quoteBefore = $instrument->fresh()->getRawOriginal('latest_price');
    $investmentPreview = app(PreviewInvestment::class)->handle($user, $plan, [
        'period' => '2026-01', 'unit_price' => '120', 'price_date' => '2026-01-20',
    ]);
    $successorPreview = app(PreviewSuccessor::class)->handle($user, $plan, [
        'monthly_increment' => '900', 'start_period' => '2026-11',
    ]);

    expect($investmentPreview['snapshot']['proposed_quantity'])->toBeGreaterThan(0)
        ->and($successorPreview['opening_target_amount'])->toBeGreaterThan(0)
        ->and(Investment::query()->count())->toBe(0)
        ->and(Plan::query()->count())->toBe(1)
        ->and($plan->fresh()->configuration_locked_at)->toBeNull()
        ->and($plan->fresh()->status)->toBe('active')
        ->and($instrument->fresh()->getRawOriginal('latest_price'))->toBe($quoteBefore);
});

it('ignores tampered period and price date when correcting a locked historical record', function () {
    $user = domainUser();
    $instrument = domainInstrument();
    $plan = domainPlan($user, $instrument, ['start_period' => '2026-01-01']);
    saveDomainInvestment($user, $plan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20', 'purchased_quantity' => '8',
    ]);
    $second = saveDomainInvestment($user, $plan, [
        'period' => '2026-02', 'unit_price' => '100', 'price_date' => '2026-02-20', 'purchased_quantity' => '0',
    ]);
    $quoteBeforeCorrection = $instrument->fresh()->getRawOriginal('latest_price');
    $quoteDateBeforeCorrection = $instrument->fresh()->getRawOriginal('latest_price_date');
    $correction = [
        'unit_price' => '80',
        'purchased_quantity' => '1',
        'period' => '2099-01',
        'price_date' => '2099-01-31',
    ];
    $preview = app(PreviewInvestment::class)->handle($user, $plan, $correction, $second);
    $corrected = app(SaveInvestment::class)->handle($user, $plan, $correction, $preview['fingerprint'], $second);

    expect($corrected->period->toDateString())->toBe('2026-02-01')
        ->and($corrected->price_date->toDateString())->toBe('2026-02-20')
        ->and($instrument->fresh()->getRawOriginal('latest_price'))->toBe($quoteBeforeCorrection)
        ->and($instrument->fresh()->getRawOriginal('latest_price_date'))->toBe($quoteDateBeforeCorrection)
        ->and($plan->fresh()->investments()->count())->toBe(2);
});

it('rejects correction or deletion of a record that is no longer the latest', function () {
    $user = domainUser();
    $plan = domainPlan($user, domainInstrument(), ['start_period' => '2026-01-01']);
    $first = saveDomainInvestment($user, $plan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20', 'purchased_quantity' => '8',
    ]);
    $latest = saveDomainInvestment($user, $plan, [
        'period' => '2026-03', 'unit_price' => '110', 'price_date' => '2026-03-20', 'purchased_quantity' => '1',
    ]);
    $firstBefore = $first->fresh()->getRawOriginal();

    expect(fn () => app(PreviewInvestment::class)->handle($user, $plan, [
        'unit_price' => '90', 'purchased_quantity' => '0',
    ], $first))->toThrow(ValidationException::class);
    expect(fn () => app(DeleteInvestment::class)->handle($user, $first, DeleteInvestment::fingerprint($plan->fresh(), $first)))
        ->toThrow(ValidationException::class);

    expect($first->fresh()->getRawOriginal())->toBe($firstBefore)
        ->and($latest->fresh())->not->toBeNull()
        ->and($plan->fresh()->investments()->count())->toBe(2);
});

it('lets the most recent same-date save win while an older record cannot roll back the shared quote', function () {
    $user = domainUser();
    $instrument = domainInstrument();
    $firstPlan = domainPlan($user, $instrument, ['start_period' => '2026-01-01']);
    $secondPlan = domainPlan($user, $instrument, ['start_period' => '2026-01-01']);

    saveDomainInvestment($user, $firstPlan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20', 'purchased_quantity' => '8',
    ]);
    saveDomainInvestment($user, $secondPlan, [
        'period' => '2026-01', 'unit_price' => '101', 'price_date' => '2026-01-20', 'purchased_quantity' => '7',
    ]);

    expect($instrument->fresh()->latest_price)->toBe('101');

    saveDomainInvestment($user, $firstPlan, [
        'period' => '2026-02', 'unit_price' => '99', 'price_date' => '2026-01-19', 'purchased_quantity' => '0',
    ]);

    expect($instrument->fresh()->latest_price)->toBe('101')
        ->and($instrument->fresh()->latest_price_date->toDateString())->toBe('2026-01-20')
        ->and($firstPlan->fresh()->latestInvestment->unit_price)->toBe('99');
});

it('rejects stale plan configuration and deletion confirmations without writing', function () {
    $user = domainUser();
    $instrument = domainInstrument();
    $plan = domainPlan($user, $instrument);
    $oldFingerprint = $plan->stateFingerprint();
    $updated = app(UpdatePlanConfiguration::class)->handle($user, $plan, [
        'instrument_id' => $instrument->id,
        'monthly_increment' => '750.00',
        'start_period' => '2026-02',
        'opening_mode' => 'zero',
    ], $oldFingerprint);

    expect(fn () => app(UpdatePlanConfiguration::class)->handle($user, $updated, [
        'instrument_id' => $instrument->id,
        'monthly_increment' => '750.00',
        'start_period' => '2026-03',
        'opening_mode' => 'zero',
    ], $oldFingerprint))->toThrow(ValidationException::class);
    expect(fn () => app(DeletePlan::class)->handle($user, $updated, $oldFingerprint))
        ->toThrow(ValidationException::class);

    $fresh = $updated->fresh();
    expect(Plan::query()->count())->toBe(1)
        ->and($fresh->start_period->toDateString())->toBe('2026-02-01')
        ->and($fresh->status)->toBe('active');
});

it('rejects a stale successor preview after the predecessor advances without closing it', function () {
    $this->travelTo('2026-11-12 10:00:00');
    $user = domainUser();
    $instrument = domainInstrument();
    $plan = domainPlan($user, $instrument, ['start_period' => '2026-01-01']);
    saveDomainInvestment($user, $plan, [
        'period' => '2026-01', 'unit_price' => '100', 'price_date' => '2026-01-20', 'purchased_quantity' => '8',
    ]);
    $successorInput = ['monthly_increment' => '900', 'start_period' => '2026-11'];
    $preview = app(PreviewSuccessor::class)->handle($user, $plan, $successorInput);
    saveDomainInvestment($user, $plan, [
        'period' => '2026-10', 'unit_price' => '105', 'price_date' => '2026-10-20', 'purchased_quantity' => '1',
    ]);

    expect(fn () => app(CreateSuccessor::class)->handle($user, $plan, $successorInput, $preview['fingerprint']))
        ->toThrow(ValidationException::class);

    expect($plan->fresh()->status)->toBe('active')
        ->and($plan->fresh()->closed_period)->toBeNull()
        ->and(Plan::query()->count())->toBe(1)
        ->and($plan->fresh()->investments()->count())->toBe(2);
});

it('rejects an invalid successor before changing either plan', function () {
    $this->travelTo('2026-11-12 10:00:00');
    $user = domainUser();
    $plan = domainPlan($user, domainInstrument(), ['start_period' => '2026-01-01']);
    $input = ['monthly_increment' => '900', 'start_period' => '2026-01'];
    $fingerprint = app(PreviewSuccessor::class)->handle($user, $plan, [
        'monthly_increment' => '900', 'start_period' => '2026-11',
    ])['fingerprint'];

    expect(fn () => app(CreateSuccessor::class)->handle($user, $plan, $input, $fingerprint))
        ->toThrow(ValidationException::class);

    expect($plan->fresh()->status)->toBe('active')
        ->and($plan->fresh()->closed_period)->toBeNull()
        ->and(Plan::query()->count())->toBe(1);
});
