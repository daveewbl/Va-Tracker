<?php

namespace App\Actions\Concerns;

use App\Models\Plan;

final class InvestmentFingerprint
{
    /** @param array<string, int|string|null> $input */
    public static function make(Plan $plan, array $input, ?int $editingId = null): string
    {
        $latest = $plan->investments()->orderByDesc('period')->orderByDesc('id')->first();

        return hash('sha256', json_encode([
            'plan' => $plan->stateFingerprint(),
            'latest' => $latest?->getRawOriginal(),
            'inputs' => $input,
            'editing_id' => $editingId,
        ], JSON_THROW_ON_ERROR));
    }
}
