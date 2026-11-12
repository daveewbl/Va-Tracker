<x-filament-panels::page>
    @php
        $activePlans = $plans->where('status', 'active');
        $closedPlans = $plans->where('status', 'closed');
    @endphp


    @if ($planPreview !== [])
        <x-filament::section>
            <x-slot name="heading">{{ $configurationPlanId === null ? 'Riepilogo di apertura' : 'Riepilogo della modifica' }}</x-slot>
            <p class="mb-4">{{ $previewInstrument?->ticker }} · {{ $previewInstrument?->name }} — da {{ substr($planPreview['start_period'], 5, 2) }}/{{ substr($planPreview['start_period'], 0, 4) }}, incremento mensile {{ \App\Support\Decimal::formatMoney($planPreview['monthly_increment']) }} €.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>Obiettivo di apertura <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['opening_target_amount']) }} €</strong></div>
                <div>Primo periodo registrabile <strong class="block">{{ substr($planPreview['first_period'], 5, 2) }}/{{ substr($planPreview['first_period'], 0, 4) }}</strong></div>
                <div>Obiettivo del primo periodo <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['first_target_amount']) }} €</strong></div>
                <div>Quote di apertura <strong class="block">{{ $planPreview['opening_quantity'] }}</strong></div>
                <div>Capitale di apertura <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['opening_invested_amount']) }} €</strong></div>
                <div>Liquidità virtuale di apertura <strong class="block">{{ \App\Support\Decimal::formatMoney($planPreview['opening_virtual_liquidity']) }} €</strong></div>
            </div>
            <p class="my-4 text-sm text-gray-500 dark:text-gray-400">Il riepilogo non ha salvato dati. Confermando non verranno create rilevazioni né modificate quotazioni.</p>
            {{ $this->confirmPlanAction }}
        </x-filament::section>
    @endif

    <div class="space-y-8">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-7">
            <div class="grid gap-6 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">Piani di accumulo</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">Un percorso alla volta, mese dopo mese</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">Il valore del portafoglio usa l’ultima quotazione inserita. Ogni PAC conserva il proprio andamento e le proprie rilevazioni.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-primary-50 p-4 dark:bg-primary-950/60">
                        <p class="text-xs font-medium text-primary-700 dark:text-primary-300">Attivi</p>
                        <p class="mt-1 text-2xl font-semibold text-primary-950 dark:text-white">{{ $activePlans->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Conclusi</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $closedPlans->count() }}</p>
                    </div>
                </div>
            </div>
        </section>

        @forelse (['active' => ['Attivi', $activePlans], 'closed' => ['Conclusi', $closedPlans]] as $status => [$label, $group])
            @if ($group->isNotEmpty())
                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $label }}</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $group->count() }} {{ $group->count() === 1 ? 'piano' : 'piani' }}</span>
                    </div>
                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach ($group as $plan)
                            @php($portfolioValue = $summaries[$plan->id]['value'])
                            <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                <div class="flex flex-col gap-4 p-5 sm:p-6">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-md bg-gray-100 px-2.5 py-1 font-mono text-xs font-semibold tracking-wide text-gray-800 dark:bg-gray-800 dark:text-gray-200">{{ $plan->instrument->ticker }}</span>
                                                @if ($plan->previous_plan_id || $plan->successor)
                                                    <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-800 dark:bg-violet-950 dark:text-violet-200">{{ $plan->previous_plan_id ? 'Successore' : 'Con successore' }}</span>
                                                @endif
                                            </div>
                                            <h4 class="mt-3 truncate text-lg font-semibold text-gray-950 dark:text-white">{{ $plan->instrument->name }}</h4>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Da {{ $plan->start_period->format('m/Y') }} · incremento € {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('monthly_increment')) }} / mese</p>
                                        </div>
                                        <span @class([
                                            'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $plan->status === 'active',
                                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $plan->status !== 'active',
                                        ])>{{ $plan->status === 'active' ? 'Attivo' : 'Concluso' }}</span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/80">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Quote</p>
                                            <p class="mt-1 font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($plan->quantity(), 0, ',', '.') }}</p>
                                        </div>
                                        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/80">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $plan->status === 'active' ? 'Valore attuale' : 'Ultimo valore storico' }}</p>
                                            <p class="mt-1 truncate font-semibold tabular-nums text-gray-950 dark:text-white">{{ $portfolioValue === null ? '—' : '€ '.\App\Support\Decimal::formatMoney($portfolioValue) }}</p>
                                        </div>
                                        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/80">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Capitale investito</p>
                                            <p class="mt-1 truncate font-semibold tabular-nums text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney($plan->investedAmount()) }}</p>
                                        </div>
                                        <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/80">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Liquidità virtuale</p>
                                            <p class="mt-1 truncate font-semibold tabular-nums text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney($plan->virtualLiquidity()) }}</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                                        <a href="{{ \App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->getKey()]) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200">
                                            Apri dettaglio <x-filament::icon icon="heroicon-o-arrow-right" class="size-4" />
                                        </a>
                                        <div class="flex flex-wrap gap-2">
                                            @if ($summaries[$plan->id]['editable'])
                                                <x-filament::button size="sm" color="gray" icon="heroicon-o-pencil-square" wire:click="$wire.mountAction('editConfiguration', { plan: {{ $plan->id }} })">Modifica</x-filament::button>
                                            @endif
                                            @if ($summaries[$plan->id]['deletable'])
                                                <x-filament::button size="sm" color="danger" icon="heroicon-o-trash" wire:click="$wire.mountAction('deletePlan', { plan: {{ $plan->id }} })">Elimina</x-filament::button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @empty
        @endforelse

        @if ($plans->isEmpty())
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="size-6" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-gray-950 dark:text-white">Il tuo primo PAC parte da qui</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600 dark:text-gray-400">Scegli un titolo, imposta l’incremento mensile e visualizza il riepilogo prima di aprire il piano.</p>
                <div class="mt-5"><x-filament::button icon="heroicon-o-plus" wire:click="$wire.mountAction('openPlan')">Apri un PAC</x-filament::button></div>
            </section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
