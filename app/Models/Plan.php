<?php

namespace App\Models;

use Cknow\Money\Casts\MoneyIntegerCast;
use Database\Factories\PlanFactory;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;
use OverflowException;

#[Fillable([
    'user_id', 'instrument_id', 'monthly_increment', 'start_period', 'status', 'previous_plan_id',
    'opening_period', 'opening_quantity', 'opening_invested_amount', 'opening_target_amount',
    'opening_virtual_liquidity', 'closed_period', 'configuration_locked_at',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_plan_id');
    }

    public function successor(): HasOne
    {
        return $this->hasOne(self::class, 'previous_plan_id');
    }

    public function latestInvestment(): HasOne
    {
        return $this->hasOne(Investment::class)->ofMany('period', 'max');
    }

    public function quantity(): int
    {
        return (int) ($this->latestInvestment?->quantity_after ?? $this->opening_quantity);
    }

    public function investedAmount(): int
    {
        return (int) ($this->latestInvestment?->getRawOriginal('invested_amount_after') ?? $this->raw('opening_invested_amount'));
    }

    public function virtualLiquidity(): int
    {
        return (int) ($this->latestInvestment?->getRawOriginal('virtual_liquidity_after') ?? $this->raw('opening_virtual_liquidity'));
    }

    public function targetFor(string $period): int
    {
        $periodDate = $this->month($period);
        $startDate = $this->month((string) $this->raw('start_period'));
        $increment = (int) $this->raw('monthly_increment');

        if ($this->opening_period !== null) {
            $openingDate = $this->month((string) $this->raw('opening_period'));

            if ($periodDate < $openingDate) {
                throw new InvalidArgumentException('The requested period precedes the plan opening.');
            }

            return $this->checkedTarget(
                (int) $this->raw('opening_target_amount'),
                $increment,
                $this->monthsBetween($openingDate, $periodDate),
            );
        }

        if ($periodDate < $startDate) {
            throw new InvalidArgumentException('The requested period precedes the plan start.');
        }

        return $this->checkedTarget(0, $increment, $this->monthsBetween($startDate, $periodDate) + 1);
    }

    public function firstRecordablePeriod(): string
    {
        $lastPeriod = $this->latestInvestment?->getRawOriginal('period') ?? $this->raw('opening_period');

        if ($lastPeriod !== null) {
            return $this->month((string) $lastPeriod)->modify('first day of next month')->format('Y-m-01');
        }

        return $this->month((string) $this->raw('start_period'))->format('Y-m-01');
    }

    public function stateFingerprint(): string
    {
        $planAttributes = $this->getRawOriginal();
        ksort($planAttributes);
        $latestAttributes = $this->latestInvestment?->getRawOriginal();

        if ($latestAttributes !== null) {
            ksort($latestAttributes);
        }

        return hash('sha256', json_encode([
            'plan' => $planAttributes,
            'latest_investment' => $latestAttributes,
        ], JSON_THROW_ON_ERROR));
    }

    public function configurationEditable(): bool
    {
        return $this->status === 'active'
            && $this->previous_plan_id === null
            && ! $this->successor()->exists()
            && $this->configuration_locked_at === null
            && ! $this->investments()->exists();
    }

    public function deletable(): bool
    {
        return $this->status === 'active'
            && $this->previous_plan_id === null
            && ! $this->successor()->exists()
            && ! $this->investments()->exists();
    }

    protected function casts(): array
    {
        return [
            'monthly_increment' => MoneyIntegerCast::class.':EUR',
            'opening_invested_amount' => MoneyIntegerCast::class.':EUR',
            'opening_target_amount' => MoneyIntegerCast::class.':EUR',
            'opening_virtual_liquidity' => MoneyIntegerCast::class.':EUR',
            'opening_period' => 'date:Y-m-d',
            'start_period' => 'date:Y-m-d',
            'closed_period' => 'date:Y-m-d',
            'configuration_locked_at' => 'datetime',
            'opening_quantity' => 'integer',
        ];
    }

    private function raw(string $key): mixed
    {
        return $this->getRawOriginal($key) ?? $this->getAttributeFromArray($key);
    }

    private function month(string $period): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $period);

        if ($date === false || $date->format('Y-m-d') !== $period || $date->format('d') !== '01') {
            throw new InvalidArgumentException('Periods must be valid dates on the first day of a month.');
        }

        return $date;
    }

    private function monthsBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return ((int) $end->format('Y') - (int) $start->format('Y')) * 12
            + ((int) $end->format('n') - (int) $start->format('n'));
    }

    private function checkedTarget(int $openingTarget, int $increment, int $periodCount): int
    {
        if ($periodCount !== 0 && $increment > intdiv(PHP_INT_MAX - $openingTarget, $periodCount)) {
            throw new OverflowException('The cumulative target exceeds the supported integer range.');
        }

        return $openingTarget + $increment * $periodCount;
    }
}
