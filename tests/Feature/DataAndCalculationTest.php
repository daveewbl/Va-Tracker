<?php

use App\Models\Instrument;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use App\Services\ValueAveragingCalculator;
use App\Support\Decimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('converts and formats money and unit prices without floating point values', function () {
    expect(Decimal::money('1234.56'))->toBe(123456)
        ->and(Decimal::money('-0.01'))->toBe(-1)
        ->and(Decimal::price('1.234567'))->toBe(1234567)
        ->and(Decimal::formatMoney(-123456))->toBe('-1234.56')
        ->and(Decimal::formatPrice(1234000))->toBe('1.234');
});

it('rejects decimal inputs beyond the supported precision and integer range', function () {
    expect(fn () => Decimal::money('1.001'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Decimal::price('1.0000001'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Decimal::price('0'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Decimal::multiply(PHP_INT_MAX, 2))->toThrow(OverflowException::class);
});

it('stores normalized instruments and exact unit prices', function () {
    $instrument = Instrument::factory()->create([
        'ticker' => ' swda ',
        'latest_price' => '1.234567',
        'latest_price_date' => '2026-11-10',
    ]);

    expect($instrument->ticker)->toBe('SWDA')
        ->and($instrument->latest_price)->toBe('1.234567')
        ->and((int) $instrument->getRawOriginal('latest_price'))->toBe(1234567);
});

it('enforces ticker uniqueness after model normalization', function () {
    Instrument::factory()->create(['ticker' => 'swda']);

    expect(fn () => Instrument::factory()->create(['ticker' => ' SWDA ']))
        ->toThrow(QueryException::class);
});

it('calculates the documented positive, negative and rounding scenarios', function () {
    $calculator = new ValueAveragingCalculator;

    $inRange = $calculator->calculate(20, 300000, 75000, 130000000, 3, 100000, 0);
    $negative = $calculator->calculate(20, 300000, 75000, 160000000, 0, 100000, 0);
    $rounding = $calculator->calculate(0, 75000, 75000, 100000000, 8, 0, 0);

    expect($inRange['portfolio_value_before'])->toBe(260000)
        ->and($inRange['theoretical_investment'])->toBe(40000)
        ->and($inRange['proposed_quantity'])->toBe(3)
        ->and($inRange['proposed_amount'])->toBe(39000)
        ->and($inRange['invested_amount_after'])->toBe(139000)
        ->and($inRange['range_status'])->toBe('in_range')
        ->and($negative['theoretical_investment'])->toBe(-20000)
        ->and($negative['proposed_quantity'])->toBe(0)
        ->and($negative['quantity_after'])->toBe(20)
        ->and($negative['virtual_liquidity_after'])->toBe(20000)
        ->and($negative['range_status'])->toBe('virtual_liquidity')
        ->and($rounding['proposed_quantity'])->toBe(8)
        ->and($rounding['proposed_amount'])->toBe(80000)
        ->and($rounding['rounding_exceeds_increment'])->toBeTrue();
});

it('classifies the unrounded theoretical amount before rounding to cents', function () {
    $result = app(ValueAveragingCalculator::class)->calculate(
        1,
        75001,
        75000,
        5100,
        0,
        0,
        0,
    );

    expect($result['theoretical_investment'])->toBe(75000)
        ->and($result['range_status'])->toBe('above_increment')
        ->and($result['rounding_exceeds_increment'])->toBeFalse();
});

it('uses the monthly calendar for opening targets and the first recordable period', function () {
    $plan = Plan::factory()->create([
        'start_period' => '2026-01-01',
        'opening_period' => '2026-10-01',
        'opening_target_amount' => '7500.00',
    ]);

    expect($plan->targetFor('2026-11-01'))->toBe(825000)
        ->and($plan->firstRecordablePeriod())->toBe('2026-11-01');
});

it('calculates the target by calendar month even when earlier periods were skipped', function () {
    $plan = Plan::factory()->create();
    $storedPlan = Plan::query()->findOrFail($plan->getKey());

    expect($plan->targetFor('2026-03-01'))->toBe(225000)
        ->and($plan->stateFingerprint())->toBe($storedPlan->stateFingerprint())
        ->and($plan->firstRecordablePeriod())->toBe('2026-01-01');

    $plan->investments()->create([
        'period' => '2026-01-01',
        'unit_price' => '100.000000',
        'price_date' => '2026-01-01',
        'quantity_before' => 0,
        'target_amount' => '750.00',
        'portfolio_value_before' => '0.00',
        'theoretical_investment' => '750.00',
        'proposed_quantity' => 8,
        'proposed_amount' => '800.00',
        'purchased_quantity' => 8,
        'purchased_amount' => '800.00',
        'quantity_after' => 8,
        'invested_amount_after' => '800.00',
        'virtual_liquidity_increase' => '0.00',
        'virtual_liquidity_after' => '0.00',
        'range_status' => 'in_range',
        'rounding_exceeds_increment' => true,
    ]);
    $plan->refresh();

    expect($plan->firstRecordablePeriod())->toBe('2026-02-01');
});

it('enforces the one-investment-per-plan-month and one-successor constraints', function () {
    $plan = Plan::factory()->create();
    Investment::factory()->for($plan)->create(['period' => '2026-01-01']);

    expect(fn () => Investment::factory()->for($plan)->create(['period' => '2026-01-01']))
        ->toThrow(QueryException::class);

    Plan::factory()->create(['previous_plan_id' => $plan->id]);

    expect(fn () => Plan::factory()->create(['previous_plan_id' => $plan->id]))
        ->toThrow(QueryException::class);
});

it('prevents deleting instruments, plans and users while restrictive dependents exist', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();
    $plan = Plan::factory()->for($user)->for($instrument)->create();
    Investment::factory()->for($plan)->create();

    expect(fn () => $instrument->delete())->toThrow(QueryException::class)
        ->and(fn () => $plan->delete())->toThrow(QueryException::class)
        ->and(fn () => $user->delete())->toThrow(QueryException::class);
});

it('rejects fractional share quantities and prices in stored investment snapshots', function () {
    $investment = Investment::factory()->create();

    expect(fn () => DB::table('investments')->where('id', $investment->id)->update(['unit_price' => 100000.5]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('investments')->where('id', $investment->id)->update(['quantity_after' => 8.5]))
        ->toThrow(QueryException::class);
});

it('rejects fractional values and malformed month dates at the SQLite boundary', function () {
    $instrument = Instrument::factory()->create();
    $userId = User::factory()->create()->id;

    expect(fn () => DB::table('plans')->insert([
        'user_id' => $userId,
        'instrument_id' => $instrument->id,
        'monthly_increment' => 1.5,
        'start_period' => '2026-01-01',
        'status' => 'active',
        'opening_quantity' => 0,
        'opening_invested_amount' => 0,
        'opening_target_amount' => 0,
        'opening_virtual_liquidity' => 0,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('plans')->insert([
        'user_id' => $userId,
        'instrument_id' => $instrument->id,
        'monthly_increment' => 75000,
        'start_period' => '2026-99-01',
        'status' => 'active',
        'opening_quantity' => 0,
        'opening_invested_amount' => 0,
        'opening_target_amount' => 0,
        'opening_virtual_liquidity' => 0,
    ]))->toThrow(QueryException::class);
});
