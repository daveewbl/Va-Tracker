<?php

namespace App\Actions;

use App\Actions\Concerns\PlanConfiguration;
use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePlanConfiguration
{
    use AsAction;
    use PlanConfiguration;
    use PlanState;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input */
    public function handle(User $user, Plan $plan, array $input, string $expectedFingerprint): Plan
    {
        return DB::transaction(function () use ($user, $plan, $input, $expectedFingerprint): Plan {
            $current = $this->ownedPlan($user, $plan);

            if (! $current->configurationEditable()) {
                throw ValidationException::withMessages(['plan' => 'La configurazione è bloccata perché il PAC è già iniziato o collegato a un altro piano.']);
            }

            if (! hash_equals($expectedFingerprint, $current->stateFingerprint())) {
                $this->fingerprintMismatch();
            }

            $values = $this->configurationValues(
                $input,
                (int) $current->getRawOriginal('monthly_increment'),
            );

            $current->instrument_id = $values['instrument_id'];
            $current->start_period = $values['start_period'];
            $current->opening_period = $values['opening_period'] === '' ? null : $values['opening_period'];
            $current->opening_quantity = $values['opening_quantity'];
            $current->opening_invested_amount = $values['opening_invested_amount'];
            $current->opening_target_amount = $values['opening_target_amount'];
            $current->opening_virtual_liquidity = $values['opening_virtual_liquidity'];
            $current->save();

            return $current->refresh();
        }, attempts: 5);
    }
}
