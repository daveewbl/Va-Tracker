<?php

namespace App\Filament\Pages;

use App\Models\Plan;
use App\Support\Decimal;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Panoramica';

    protected string $view = 'filament.pages.dashboard';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newPlan')->label('Nuovo PAC')->url(fn (): string => Plans::getUrl(['create' => 1])),
            Action::make('instruments')->label('Titoli e quotazioni')->color('gray')->url(fn (): string => Instruments::getUrl()),
        ];
    }

    protected function getViewData(): array
    {
        $period = now()->startOfMonth()->toDateString();
        $plans = Plan::query()
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->with(['instrument', 'latestInvestment'])
            ->orderBy('instrument_id')
            ->orderBy('start_period')
            ->orderBy('id')
            ->get();
        $invested = 0;
        $value = 0;
        $virtual = 0;
        $missing = 0;
        $rows = [];

        foreach ($plans as $plan) {
            $quantity = $plan->quantity();
            $capital = $plan->investedAmount();
            $liquidity = $plan->virtualLiquidity();
            $price = $plan->instrument->getRawOriginal('latest_price');
            $valuation = $quantity === 0 ? 0 : ($price === null ? null : Decimal::value($quantity, (int) $price));
            $last = $plan->latestInvestment?->period?->format('Y-m-d');
            $eligible = $period >= $plan->firstRecordablePeriod() && ($last === null || $period > $last);
            $status = match (true) {
                $last === $period => 'Rilevazione salvata',
                $eligible => 'Da rilevare',
                $period < $plan->start_period->format('Y-m-d') => 'Non ancora iniziato',
                default => 'Periodo già coperto',
            };
            $rows[] = [
                'plan' => $plan,
                'quantity' => $quantity,
                'capital' => $capital,
                'valuation' => $valuation,
                'target' => $period >= $plan->start_period->format('Y-m-d')
                    && ($plan->opening_period === null || $period >= $plan->opening_period->format('Y-m-d'))
                        ? $plan->targetFor($period) : null,
                'last' => $last,
                'eligible' => $eligible,
                'status' => $status,
            ];
            $invested = Decimal::add($invested, $capital);
            $virtual = Decimal::add($virtual, $liquidity);
            if ($valuation === null) {
                $missing++;
            } else {
                $value = Decimal::add($value, $valuation);
            }
        }

        return [
            'rows' => $rows,
            'currentPeriod' => now()->locale('it')->translatedFormat('F Y'),
            'totalInvested' => $invested,
            'totalValue' => $value,
            'totalVirtual' => $virtual,
            'missingPrices' => $missing,
            'hasClosedPlans' => Plan::query()->where('user_id', Auth::id())->where('status', 'closed')->exists(),
        ];
    }
}
