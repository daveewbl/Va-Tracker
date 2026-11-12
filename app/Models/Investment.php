<?php

namespace App\Models;

use App\Casts\UnitPriceCast;
use Cknow\Money\Casts\MoneyIntegerCast;
use Database\Factories\InvestmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_id', 'period', 'unit_price', 'price_date', 'quantity_before', 'target_amount',
    'portfolio_value_before', 'theoretical_investment', 'proposed_quantity', 'proposed_amount',
    'purchased_quantity', 'purchased_amount', 'quantity_after', 'invested_amount_after',
    'virtual_liquidity_increase', 'virtual_liquidity_after', 'range_status', 'rounding_exceeds_increment',
])]
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory;

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    protected function casts(): array
    {
        return [
            'period' => 'date:Y-m-d',
            'price_date' => 'date:Y-m-d',
            'unit_price' => UnitPriceCast::class,
            'quantity_before' => 'integer',
            'target_amount' => MoneyIntegerCast::class.':EUR',
            'portfolio_value_before' => MoneyIntegerCast::class.':EUR',
            'theoretical_investment' => MoneyIntegerCast::class.':EUR',
            'proposed_quantity' => 'integer',
            'proposed_amount' => MoneyIntegerCast::class.':EUR',
            'purchased_quantity' => 'integer',
            'purchased_amount' => MoneyIntegerCast::class.':EUR',
            'quantity_after' => 'integer',
            'invested_amount_after' => MoneyIntegerCast::class.':EUR',
            'virtual_liquidity_increase' => MoneyIntegerCast::class.':EUR',
            'virtual_liquidity_after' => MoneyIntegerCast::class.':EUR',
            'rounding_exceeds_increment' => 'boolean',
        ];
    }
}
