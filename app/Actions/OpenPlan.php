<?php

namespace App\Actions;

use App\Actions\Concerns\PlanConfiguration;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OpenPlan
{
    use AsAction;
    use PlanConfiguration;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input */
    public function handle(User $user, array $input): Plan
    {
        return DB::transaction(function () use ($user, $input): Plan {
            $values = $this->configurationValues($input);
            $plan = new Plan;
            $plan->user_id = $user->getKey();
            $plan->instrument_id = $values['instrument_id'];
            $plan->monthly_increment = $values['monthly_increment'];
            $plan->start_period = $values['start_period'];
            $plan->status = 'active';
            $plan->opening_period = $values['opening_period'] === '' ? null : $values['opening_period'];
            $plan->opening_quantity = $values['opening_quantity'];
            $plan->opening_invested_amount = $values['opening_invested_amount'];
            $plan->opening_target_amount = $values['opening_target_amount'];
            $plan->opening_virtual_liquidity = $values['opening_virtual_liquidity'];
            $plan->save();

            return $plan->refresh();
        }, attempts: 5);
    }
}
