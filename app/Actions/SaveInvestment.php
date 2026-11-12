<?php

namespace App\Actions;

use App\Actions\Concerns\InvestmentCalculation;
use App\Actions\Concerns\InvestmentFingerprint;
use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Data\InvestmentData;
use App\Models\Instrument;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveInvestment
{
    use AsAction;
    use InvestmentCalculation;
    use PlanState;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input */
    public function handle(User $user, Plan $plan, array $input, string $expectedFingerprint, ?Investment $editing = null): Investment
    {
        return DB::transaction(function () use ($user, $plan, $input, $expectedFingerprint, $editing): Investment {
            $currentPlan = $this->ownedPlan($user, $plan);
            $this->assertActive($currentPlan);
            $currentEditing = null;
            $rules = [
                'unit_price' => ['required', 'string'],
                'purchased_quantity' => ['required'],
            ];

            if ($editing) {
                $currentEditing = $this->assertLatestInvestment($currentPlan, $editing);
                $period = $currentEditing->period->toDateString();
                $priceDate = $currentEditing->price_date->toDateString();
            } else {
                $rules['period'] = ['required', 'string'];
                $rules['price_date'] = ['required', 'date_format:Y-m-d'];
            }

            $data = $this->validate($input, $rules);
            $period = $editing ? $period : $this->period($data['period'], 'period');
            $priceDate = $editing ? $priceDate : $this->date($data['price_date'], 'price_date');
            $price = $this->price($data['unit_price'], 'unit_price');
            $purchasedQuantity = $this->quantity($data['purchased_quantity'], 'purchased_quantity');

            if (! $editing) {
                $latest = $this->latestInvestment($currentPlan);

                if ($period < $currentPlan->firstRecordablePeriod() || ($latest && $period <= $latest->period->toDateString())) {
                    throw ValidationException::withMessages(['period' => 'Il periodo deve essere successivo all’ultima rilevazione e compatibile con l’apertura del PAC.']);
                }

                if ($currentPlan->investments()->where('period', $period)->exists()) {
                    throw ValidationException::withMessages(['period' => 'Esiste già una rilevazione per questo periodo.']);
                }
            }

            $investmentData = new InvestmentData($period, $price, $priceDate, $purchasedQuantity);
            $fingerprint = InvestmentFingerprint::make($currentPlan, $investmentData->fingerprintValues(), $currentEditing?->getKey());

            if (! hash_equals($expectedFingerprint, $fingerprint)) {
                $this->fingerprintMismatch();
            }

            [$quantityBefore, $investedBefore, $liquidityBefore] = $this->positionBefore($currentPlan, $currentEditing);
            $snapshot = $this->calculateSnapshot(
                $currentPlan,
                $investmentData->period,
                $investmentData->unitPriceMicros,
                $investmentData->purchasedQuantity,
                $quantityBefore,
                $investedBefore,
                $liquidityBefore,
            );

            $investment = $currentEditing ?? new Investment;
            $investment->plan_id = $currentPlan->getKey();
            $investment->period = $investmentData->period;
            $investment->unit_price = Decimal::formatPrice($investmentData->unitPriceMicros);
            $investment->price_date = $investmentData->priceDate;

            foreach ([
                'quantity_before',
                'target_amount',
                'portfolio_value_before',
                'theoretical_investment',
                'proposed_quantity',
                'proposed_amount',
                'purchased_quantity',
                'purchased_amount',
                'quantity_after',
                'invested_amount_after',
                'virtual_liquidity_increase',
                'virtual_liquidity_after',
                'range_status',
                'rounding_exceeds_increment',
            ] as $field) {
                $investment->{$field} = $snapshot[$field];
            }

            $investment->save();

            if ($currentPlan->configuration_locked_at === null) {
                $currentPlan->configuration_locked_at = now();
                $currentPlan->save();
            }

            if ($currentEditing === null) {
                $instrument = Instrument::query()->findOrFail($currentPlan->instrument_id);
                $existingQuoteDate = $instrument->latest_price_date?->toDateString();

                if ($existingQuoteDate === null || $priceDate >= $existingQuoteDate) {
                    $instrument->latest_price = Decimal::formatPrice($price);
                    $instrument->latest_price_date = $priceDate;
                    $instrument->save();
                }
            }

            return $investment->refresh();
        }, attempts: 5);
    }
}
