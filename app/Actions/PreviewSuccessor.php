<?php

namespace App\Actions;

use App\Actions\Concerns\PlanState;
use App\Actions\Concerns\SuccessorState;
use App\Actions\Concerns\ValidatesVaInput;
use App\Models\Plan;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class PreviewSuccessor
{
    use AsAction;
    use PlanState;
    use SuccessorState;
    use ValidatesVaInput;

    /** @param array<string, mixed> $input
     * @return array{fingerprint: string, monthly_increment: int, start_period: string, closed_period: string, opening_quantity: int, opening_invested_amount: int, opening_target_amount: int, opening_virtual_liquidity: int, first_target_amount: int}
     */
    public function handle(User $user, Plan $plan, array $input): array
    {
        $current = $this->ownedPlan($user, $plan);
        $this->assertActive($current);

        if ($current->successor()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Questo PAC ha già un successore.']);
        }

        $values = $this->successorValues($current, $input);

        try {
            $firstTargetAmount = Decimal::add($values['opening_target_amount'], $values['monthly_increment']);
        } catch (\OverflowException) {
            throw ValidationException::withMessages(['monthly_increment' => 'L’obiettivo supera i limiti numerici supportati.']);
        }

        return [
            'fingerprint' => $this->successorFingerprint($current, $values),
            ...$values,
            'first_target_amount' => $firstTargetAmount,
        ];
    }
}
