<?php

namespace App\Actions;

use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\SuccessorState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateSuccessor
{
    use AsAction;
    use PlanState;
    use SuccessorState;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input */
    public function handle(User $user, Plan $plan, array $input, string $expectedFingerprint): Plan
    {
        return DB::transaction(function () use ($user, $plan, $input, $expectedFingerprint): Plan {
            $predecessor = $this->ownedPlan($user, $plan);
            $this->assertActive($predecessor);

            if ($predecessor->successor()->exists()) {
                throw ValidationException::withMessages(['plan' => 'Questo PAC ha già un successore.']);
            }

            $values = $this->successorValues($predecessor, $input);

            if (! hash_equals($expectedFingerprint, $this->successorFingerprint($predecessor, $values))) {
                $this->fingerprintMismatch();
            }

            $predecessor->status = 'closed';
            $predecessor->closed_period = $values['closed_period'];
            $predecessor->save();

            $successor = new Plan;
            $successor->user_id = $predecessor->user_id;
            $successor->instrument_id = $predecessor->instrument_id;
            $successor->monthly_increment = $values['monthly_increment'];
            $successor->start_period = $values['start_period'];
            $successor->status = 'active';
            $successor->previous_plan_id = $predecessor->getKey();
            $successor->opening_period = $values['closed_period'];
            $successor->opening_quantity = $values['opening_quantity'];
            $successor->opening_invested_amount = $values['opening_invested_amount'];
            $successor->opening_target_amount = $values['opening_target_amount'];
            $successor->opening_virtual_liquidity = $values['opening_virtual_liquidity'];
            $successor->configuration_locked_at = now();
            $successor->save();

            return $successor->refresh();
        }, attempts: 5);
    }
}
