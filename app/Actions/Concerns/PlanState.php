<?php

namespace App\Actions\Concerns;

use App\Models\Investment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

trait PlanState
{
    private function ownedPlan(User $user, Plan $plan): Plan
    {
        $current = Plan::query()->find($plan->getKey());

        if (! $current) {
            throw ValidationException::withMessages(['plan' => 'Il PAC non è più disponibile.']);
        }

        if ((int) $current->user_id !== (int) $user->getKey()) {
            throw new AuthorizationException('Non puoi accedere a questo PAC.');
        }

        return $current;
    }

    private function assertActive(Plan $plan): void
    {
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages(['plan' => 'Il PAC è chiuso e non può essere modificato.']);
        }
    }

    private function latestInvestment(Plan $plan): ?Investment
    {
        return $plan->investments()->orderByDesc('period')->orderByDesc('id')->first();
    }

    private function planStateFingerprint(Plan $plan): string
    {
        $latest = $this->latestInvestment($plan);

        return hash('sha256', json_encode([
            'plan' => $plan->stateFingerprint(),
            'latest' => $latest?->getRawOriginal(),
        ], JSON_THROW_ON_ERROR));
    }

    private function assertLatestInvestment(Plan $plan, Investment $investment): Investment
    {
        $current = Investment::query()->find($investment->getKey());

        if (! $current || $current->plan_id !== $plan->getKey()) {
            throw ValidationException::withMessages(['investment' => 'La rilevazione non è più disponibile.']);
        }

        $latest = $this->latestInvestment($plan);

        if (! $latest || $latest->getKey() !== $current->getKey()) {
            throw ValidationException::withMessages(['investment' => 'È possibile modificare o eliminare solo l’ultima rilevazione.']);
        }

        return $current;
    }

    private function positionBefore(Plan $plan, ?Investment $editing = null): array
    {
        $latest = $editing
            ? $plan->investments()->where('period', '<', $editing->period->toDateString())->orderByDesc('period')->orderByDesc('id')->first()
            : $this->latestInvestment($plan);

        if ($latest) {
            return [
                (int) $latest->getRawOriginal('quantity_after'),
                (int) $latest->getRawOriginal('invested_amount_after'),
                (int) $latest->getRawOriginal('virtual_liquidity_after'),
            ];
        }

        return [
            (int) $plan->getRawOriginal('opening_quantity'),
            (int) $plan->getRawOriginal('opening_invested_amount'),
            (int) $plan->getRawOriginal('opening_virtual_liquidity'),
        ];
    }
}
