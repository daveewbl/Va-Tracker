<?php

namespace App\Actions;

use App\Actions\Concerns\PlanConfiguration;
use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Plan;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class PreviewPlan
{
    use AsAction;
    use PlanConfiguration;
    use PlanState;
    use ValidatesVaInput;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, int|string>
     */
    public function handle(User $user, array $input, ?Plan $plan = null): array
    {
        $current = $plan ? $this->ownedPlan($user, $plan) : null;

        if ($current && ! $current->configurationEditable()) {
            throw ValidationException::withMessages(['plan' => 'La configurazione di questo PAC è bloccata.']);
        }

        $values = $this->configurationValues($input, $current ? (int) $current->getRawOriginal('monthly_increment') : null);
        $firstPeriod = $values['opening_period'] === ''
            ? $values['start_period']
            : CarbonImmutable::parse($values['opening_period'])->addMonth()->toDateString();

        try {
            $firstTarget = Decimal::add($values['opening_target_amount'], $values['monthly_increment']);
        } catch (\OverflowException) {
            throw ValidationException::withMessages(['monthly_increment' => 'L’obiettivo supera i limiti numerici supportati.']);
        }

        return [
            ...$values,
            'first_period' => $firstPeriod,
            'first_target_amount' => $firstTarget,
        ];
    }
}
