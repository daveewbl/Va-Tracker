<?php

namespace App\Actions;

use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeletePlan
{
    use AsAction;
    use PlanState;
    use ValidatesVaInput;

    public function handle(User $user, Plan $plan, string $expectedFingerprint): void
    {
        DB::transaction(function () use ($user, $plan, $expectedFingerprint): void {
            $current = $this->ownedPlan($user, $plan);

            if (! hash_equals($expectedFingerprint, $current->stateFingerprint())) {
                $this->fingerprintMismatch();
            }

            if (! $current->deletable()) {
                throw ValidationException::withMessages(['plan' => 'Puoi eliminare soltanto un PAC attivo senza rilevazioni e senza collegamenti di prosecuzione.']);
            }

            $current->delete();
        }, attempts: 5);
    }
}
