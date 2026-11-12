<?php

namespace App\Actions;

use App\Actions\Concerns\InvestmentCalculation;
use App\Actions\Concerns\InvestmentFingerprint;
use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Data\InvestmentData;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class PreviewInvestment
{
    use AsAction;
    use InvestmentCalculation;
    use PlanState;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input
     * @return array{fingerprint: string, period: string, unit_price: string, price_date: string, snapshot: array<string, int|string|bool>}
     */
    public function handle(User $user, Plan $plan, array $input, ?Investment $editing = null): array
    {
        $currentPlan = $this->ownedPlan($user, $plan);
        $this->assertActive($currentPlan);
        $currentEditing = null;
        $rules = ['unit_price' => ['required', 'string']];

        if ($editing) {
            $currentEditing = $this->assertLatestInvestment($currentPlan, $editing);
            $rules['purchased_quantity'] = ['required'];
            $period = $currentEditing->period->toDateString();
            $priceDate = $currentEditing->price_date->toDateString();
        } else {
            $rules['period'] = ['required', 'string'];
            $rules['price_date'] = ['required', 'date_format:Y-m-d'];
            $rules['purchased_quantity'] = ['nullable'];
        }

        $data = $this->validate($input, $rules);
        $period = $editing ? $period : $this->period($data['period'], 'period');
        $priceDate = $editing ? $priceDate : $this->date($data['price_date'], 'price_date');
        $price = $this->price($data['unit_price'], 'unit_price');

        if (! $editing) {
            $latest = $this->latestInvestment($currentPlan);
            $firstPeriod = $currentPlan->firstRecordablePeriod();

            if ($period < $firstPeriod || ($latest && $period <= $latest->period->toDateString())) {
                throw ValidationException::withMessages(['period' => 'Il periodo deve essere successivo all’ultima rilevazione e compatibile con l’apertura del PAC.']);
            }

            if ($currentPlan->investments()->where('period', $period)->exists()) {
                throw ValidationException::withMessages(['period' => 'Esiste già una rilevazione per questo periodo.']);
            }
        }

        [$quantityBefore, $investedBefore, $liquidityBefore] = $this->positionBefore($currentPlan, $currentEditing);
        $proposed = $this->calculateSnapshot($currentPlan, $period, $price, 0, $quantityBefore, $investedBefore, $liquidityBefore);
        $purchasedQuantity = isset($data['purchased_quantity']) && $data['purchased_quantity'] !== ''
            ? $this->quantity($data['purchased_quantity'], 'purchased_quantity')
            : (int) $proposed['proposed_quantity'];
        $investmentData = new InvestmentData($period, $price, $priceDate, $purchasedQuantity);
        $snapshot = $this->calculateSnapshot(
            $currentPlan,
            $investmentData->period,
            $investmentData->unitPriceMicros,
            $investmentData->purchasedQuantity,
            $quantityBefore,
            $investedBefore,
            $liquidityBefore,
        );
        $fingerprint = InvestmentFingerprint::make($currentPlan, $investmentData->fingerprintValues(), $currentEditing?->getKey());

        return [
            'fingerprint' => $fingerprint,
            'period' => $investmentData->period,
            'unit_price' => Decimal::formatPrice($investmentData->unitPriceMicros),
            'price_date' => $investmentData->priceDate,
            'snapshot' => $snapshot,
        ];
    }
}
