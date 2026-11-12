<?php

namespace App\Actions;

use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteInvestment
{
    use AsAction;
    use PlanState;
    use ValidatesVaInput;

    public function handle(User $user, Investment $investment, string $expectedFingerprint): void
    {
        DB::transaction(function () use ($user, $investment, $expectedFingerprint): void {
            $currentInvestment = Investment::query()->find($investment->getKey());

            if (! $currentInvestment) {
                throw ValidationException::withMessages(['investment' => 'La rilevazione non è più disponibile.']);
            }

            $plan = Plan::query()->find($currentInvestment->plan_id);

            if (! $plan) {
                throw ValidationException::withMessages(['investment' => 'Il PAC della rilevazione non è più disponibile.']);
            }

            $plan = $this->ownedPlan($user, $plan);
            $this->assertActive($plan);
            $currentInvestment = $this->assertLatestInvestment($plan, $currentInvestment);

            if (! hash_equals($expectedFingerprint, self::fingerprint($plan, $currentInvestment))) {
                $this->fingerprintMismatch();
            }

            $currentInvestment->delete();
        }, attempts: 5);
    }

    public static function fingerprint(Plan $plan, Investment $investment): string
    {
        $latest = $plan->investments()->orderByDesc('period')->orderByDesc('id')->first();

        return hash('sha256', json_encode([
            'plan' => $plan->stateFingerprint(),
            'latest' => $latest?->getRawOriginal(),
            'investment_id' => $investment->getKey(),
            'investment' => $investment->getRawOriginal(),
        ], JSON_THROW_ON_ERROR));
    }
}
