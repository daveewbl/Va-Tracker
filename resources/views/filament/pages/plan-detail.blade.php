<x-filament-panels::page>
    @php
        $maxChartValue = max(1, (int) collect($points)->flatMap(fn ($point) => array_filter([$point['target'], $point['actual']]))->max());
        $targetPath = collect($points)->values()->map(function ($point, $index) use ($points, $maxChartValue) {
            $x = count($points) < 2 ? 450 : 56 + ($index / (count($points) - 1) * 790);
            $y = 202 - ($point['target'] / $maxChartValue * 170);
            return ($index === 0 ? 'M' : 'L').number_format($x, 1, '.', '').' '.number_format($y, 1, '.', '');
        })->implode(' ');
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ \App\Filament\Pages\Plans::getUrl() }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-primary-700 dark:text-gray-400 dark:hover:text-primary-300">
                <x-filament::icon icon="heroicon-o-arrow-left" class="size-4" /> Tutti i PAC
            </a>
            <div class="flex flex-wrap items-center gap-2">
                @if ($plan->previousPlan)
                    <a href="{{ \App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->previousPlan->getKey()]) }}" class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">← PAC precedente</a>
                @endif
                @if ($plan->successor)
                    <a href="{{ \App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->successor->getKey()]) }}" class="rounded-full bg-violet-50 px-3 py-1.5 text-xs font-medium text-violet-800 hover:bg-violet-100 dark:bg-violet-950 dark:text-violet-200">PAC successore →</a>
                @endif
                <span @class([
                    'rounded-full px-3 py-1.5 text-xs font-semibold',
                    'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $plan->status === 'active',
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $plan->status !== 'active',
                ])>{{ $plan->status === 'active' ? 'PAC attivo' : 'PAC concluso' }}</span>
            </div>
        </div>

        @if ($plan->status === 'closed')
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                Questo PAC è consultabile in sola lettura. È stato concluso al {{ $plan->closed_period?->format('m/Y') ?? '—' }}; l’eventuale successore continua la catena.
            </div>
        @endif

        @if ($plan->status === 'active' && $plan->successor === null && $this->firstCompatibleSuccessorPeriod($plan) > now()->format('Y-m'))
            <div class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-200">
                Il primo mese compatibile per passare a un nuovo incremento è {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $this->firstCompatibleSuccessorPeriod($plan))->format('m/Y') }}. Il passaggio non può essere programmato in anticipo: potrai creare il successore da quel mese.
            </div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-6 p-5 sm:p-7 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-md bg-primary-50 px-2.5 py-1 font-mono text-sm font-semibold tracking-wide text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ $plan->instrument->ticker }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $plan->start_period->format('m/Y') }} · € {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('monthly_increment')) }} al mese</span>
                    </div>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $plan->instrument->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        @if ($plan->opening_period)
                            Saldo iniziale al {{ $plan->opening_period->format('m/Y') }} · prossimo periodo registrabile {{ \Illuminate\Support\Carbon::parse($plan->firstRecordablePeriod())->format('m/Y') }}
                        @else
                            Partenza da zero · prossimo periodo registrabile {{ \Illuminate\Support\Carbon::parse($plan->firstRecordablePeriod())->format('m/Y') }}
                        @endif
                        @if ($plan->configuration_locked_at)
                            · configurazione bloccata il {{ $plan->configuration_locked_at->format('d/m/Y') }}
                        @endif
                    </p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Quotazione condivisa</p>
                    @if ($displayPrice !== null)
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">€ {{ $displayPrice }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">al {{ $displayPriceDate?->format('d/m/Y') ?? '—' }}{{ $plan->status === 'closed' ? ' · ultima rilevazione del PAC' : '' }}</p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $plan->status === 'closed' ? 'Nessun prezzo nello storico del PAC' : 'Nessuna quotazione inserita' }}</p>
                    @endif
                </div>
            </div>
        </section>

        <details class="group rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Configurazione e apertura</p>
                    <h3 class="mt-2 text-base font-semibold text-gray-950 dark:text-white">Dettagli del saldo iniziale e del calendario</h3>
                </div>
                <x-filament::icon icon="heroicon-o-chevron-down" class="size-5 text-gray-500 transition-transform group-open:rotate-180" />
            </summary>
            <div class="grid gap-3 border-t border-gray-100 p-5 dark:border-gray-800 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Modalità</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $plan->opening_period ? 'Con saldo iniziale' : 'Da zero' }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Inizio originario</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $plan->start_period->format('m/Y') }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Mese coperto all’apertura</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $plan->opening_period?->format('m/Y') ?? 'Nessuno' }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Prossimo periodo registrabile</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Carbon::parse($plan->firstRecordablePeriod())->format('m/Y') }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Quote di apertura</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ number_format((int) $plan->opening_quantity, 0, ',', '.') }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Capitale iniziale</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('opening_invested_amount')) }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Obiettivo di apertura</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('opening_target_amount')) }}</p></div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400">Liquidità virtuale iniziale</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('opening_virtual_liquidity')) }}</p></div>
            </div>
        </details>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Quote possedute</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($plan->quantity(), 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Valore indicativo</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $portfolioValue === null ? '—' : '€ '.\App\Support\Decimal::formatMoney($portfolioValue) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">alla quotazione corrente</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Capitale investito</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney($plan->investedAmount()) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Liquidità virtuale</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney($plan->virtualLiquidity()) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">indicatore, non denaro disponibile</p>
            </div>
            <div class="rounded-2xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-900 dark:bg-primary-950/50">
                <p class="text-xs font-medium text-primary-800 dark:text-primary-300">Obiettivo del periodo</p>
                <p class="mt-2 text-xl font-semibold tabular-nums text-primary-950 dark:text-white">{{ $calendarTarget === null ? '—' : '€ '.\App\Support\Decimal::formatMoney($calendarTarget) }}</p>
                <p class="mt-1 text-xs text-primary-700 dark:text-primary-300">{{ $calendarTarget === null ? 'Il piano non è ancora iniziato' : \Illuminate\Support\Carbon::parse($targetForPeriod)->format('m/Y') }}</p>
            </div>
        </section>

        @if ($this->investmentSnapshot !== [])
            <section class="rounded-2xl border border-primary-200 bg-primary-50/70 p-5 dark:border-primary-900 dark:bg-primary-950/30 sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">Anteprima senza salvataggio</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">{{ $this->editingInvestmentId ? 'Correzione' : 'Rilevazione' }} · {{ \Illuminate\Support\Carbon::parse($this->investmentInput['period'])->format('m/Y') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Prezzo € {{ $this->investmentInput['unit_price'] }} del {{ \Illuminate\Support\Carbon::parse($this->investmentInput['price_date'])->format('d/m/Y') }} · quote effettive {{ $this->investmentSnapshot['purchased_quantity'] }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {{ ($this->adjustInvestmentAction) }}
                        {{ ($this->saveInvestmentAction) }}
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Quote prima</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($this->investmentSnapshot['quantity_before'] ?? 0), 0, ',', '.') }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Valore prima dell’acquisto</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) ($this->investmentSnapshot['portfolio_value_before'] ?? 0)) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Obiettivo cumulativo</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) ($this->investmentSnapshot['target_amount'] ?? 0)) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Importo teorico VA</p><p @class(['mt-1 font-semibold', 'text-rose-700 dark:text-rose-300' => (int) ($this->investmentSnapshot['theoretical_investment'] ?? 0) < 0, 'text-gray-950 dark:text-white' => (int) ($this->investmentSnapshot['theoretical_investment'] ?? 0) >= 0])>€ {{ \App\Support\Decimal::formatMoney((int) ($this->investmentSnapshot['theoretical_investment'] ?? 0)) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Quote proposte</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ number_format((int) ($this->investmentSnapshot['proposed_quantity'] ?? 0), 0, ',', '.') }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Controvalore proposto</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) ($this->investmentSnapshot['proposed_amount'] ?? 0)) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Controvalore effettivo</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) ($this->investmentSnapshot['purchased_amount'] ?? 0)) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Liquidità virtuale dopo</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) ($this->investmentSnapshot['virtual_liquidity_after'] ?? 0)) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Quote dopo l’acquisto</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $this->investmentSnapshot['quantity_after'] }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Capitale investito dopo</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney($this->investmentSnapshot['invested_amount_after']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Incremento virtuale del mese</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney($this->investmentSnapshot['virtual_liquidity_increase']) }}</p></div>
                </div>
                <div class="mt-4 rounded-xl bg-white px-4 py-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    @if (($this->investmentSnapshot['range_status'] ?? '') === 'virtual_liquidity')
                        L’importo teorico è negativo: non vengono proposte vendite e la differenza incrementa la liquidità virtuale.
                    @elseif (($this->investmentSnapshot['range_status'] ?? '') === 'above_increment')
                        L’importo teorico supera l’incremento mensile. L’acquisto resta consentito.
                    @else
                        Importo nel range dell’incremento mensile.
                    @endif
                    @if ($this->investmentSnapshot['rounding_exceeds_increment'] ?? false)
                        <span class="font-medium">Il controvalore proposto supera l’incremento per effetto dell’arrotondamento.</span>
                    @endif
                </div>
            </section>
        @endif

        @if ($this->successorPreview !== [])
            <section class="rounded-2xl border border-violet-200 bg-violet-50/70 p-5 dark:border-violet-900 dark:bg-violet-950/30 sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">Riepilogo del passaggio</p>
                        <h3 class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">Il nuovo PAC partirà da {{ \Illuminate\Support\Carbon::parse($this->successorPreview['start_period'])->format('m/Y') }}</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Il PAC attuale si chiuderà al {{ \Illuminate\Support\Carbon::parse($this->successorPreview['closed_period'])->format('m/Y') }}. La conferma renderà non modificabile anche il suo storico.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {{ ($this->adjustSuccessorPreviewAction) }}
                        {{ ($this->createSuccessorAction) }}
                    </div>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Nuovo incremento</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['monthly_increment']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Quote trasferite</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ number_format((int) $this->successorPreview['opening_quantity'], 0, ',', '.') }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Capitale investito</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['opening_invested_amount']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Obiettivo ereditato</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['opening_target_amount']) }}</p></div>
                    <div class="rounded-xl bg-white p-4 dark:bg-gray-900"><p class="text-xs text-gray-500 dark:text-gray-400">Liquidità virtuale</p><p class="mt-1 font-semibold text-gray-950 dark:text-white">€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['opening_virtual_liquidity']) }}</p></div>
                </div>
                <p class="mt-4 rounded-xl bg-white px-4 py-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">Primo obiettivo del successore: <strong>€ {{ \App\Support\Decimal::formatMoney((int) $this->successorPreview['first_target_amount']) }}</strong>. I mesi senza rilevazione fanno avanzare l’obiettivo per calendario, senza acquisti o liquidità virtuale fittizi.</p>
            </section>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">Andamento per calendario</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">Obiettivo e valore prima dell’acquisto</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">La linea sale ogni mese. I punti mostrano il valore storico appena prima delle rilevazioni registrate; i mesi vuoti restano senza prezzo ricostruito. Il grafico mostra al massimo gli ultimi 180 mesi; lo storico completo è riportato sotto.</p>
                </div>
                <div class="flex flex-wrap gap-4 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="inline-flex items-center gap-2"><span class="h-0.5 w-5 bg-primary-600"></span>Obiettivo</span>
                    <span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full border-2 border-violet-600 bg-white dark:bg-gray-900"></span>Prima dell’acquisto</span>
                </div>
            </div>
            <div class="mt-5 overflow-x-auto">
                <svg viewBox="0 0 900 245" role="img" aria-label="Grafico dell’obiettivo per calendario e del valore prima degli acquisti" class="h-64 min-w-[720px] w-full">
                    <line x1="56" y1="32" x2="846" y2="32" class="stroke-gray-100 dark:stroke-gray-800" />
                    <line x1="56" y1="117" x2="846" y2="117" class="stroke-gray-100 dark:stroke-gray-800" />
                    <line x1="56" y1="202" x2="846" y2="202" class="stroke-gray-200 dark:stroke-gray-700" />
                    <text x="8" y="36" class="fill-gray-400 text-[10px]">€ {{ number_format($maxChartValue, 0, ',', '.') }}</text>
                    <text x="8" y="206" class="fill-gray-400 text-[10px]">€ 0</text>
                    <path d="{{ $targetPath }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="text-primary-600 dark:text-primary-400" />
                    @foreach ($points as $index => $point)
                        @php
                            $x = count($points) < 2 ? 450 : 56 + ($index / (count($points) - 1) * 790);
                            $y = $point['actual'] === null ? null : 202 - ($point['actual'] / $maxChartValue * 170);
                        @endphp
                        @if ($point['actual'] !== null)
                            <circle cx="{{ number_format($x, 1, '.', '') }}" cy="{{ number_format($y, 1, '.', '') }}" r="5" fill="white" stroke="#7c3aed" stroke-width="3">
                                <title>{{ $point['period'] }} · prima dell’acquisto € {{ \App\Support\Decimal::formatMoney($point['actual']) }}</title>
                            </circle>
                        @endif
                    @endforeach
                    @foreach (collect($points)->values()->filter(fn ($point, $index) => $index === 0 || $index === count($points) - 1) as $point)
                        @php $index = array_search($point, $points, true); $x = count($points) < 2 ? 450 : 56 + ($index / (count($points) - 1) * 790); @endphp
                        <text x="{{ number_format($x, 1, '.', '') }}" y="230" text-anchor="middle" class="fill-gray-500 text-[10px]">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $point['period'])->format('m/Y') }}</text>
                    @endforeach
                </svg>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2 border-b border-gray-100 p-5 dark:border-gray-800 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Storico completo</p>
                    <h3 class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">Rilevazioni salvate</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Ogni riga conserva la fotografia originale del calcolo. Le date dei prezzi e i periodi PAC restano distinti.</p>
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $investments->count() }} {{ $investments->count() === 1 ? 'rilevazione' : 'rilevazioni' }}</span>
            </div>

            @if ($investments->isEmpty())
                <div class="px-6 py-12 text-center">
                    <h4 class="font-semibold text-gray-950 dark:text-white">Nessuna rilevazione ancora</h4>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Le quote e gli eventuali saldi iniziali sono già visibili sopra. Il primo mese utile è {{ \Illuminate\Support\Carbon::parse($plan->firstRecordablePeriod())->format('m/Y') }}.</p>
                    @if ($plan->status === 'active')
                        <div class="mt-5"><x-filament::button icon="heroicon-o-plus" wire:click="$wire.mountAction('previewInvestment')">Prepara la prima rilevazione</x-filament::button></div>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-[1150px] w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Periodo</th><th class="px-4 py-3">Prezzo / data</th><th class="px-4 py-3">Quote prima</th><th class="px-4 py-3">Valore prima</th><th class="px-4 py-3">Obiettivo</th><th class="px-4 py-3">Teorico VA</th><th class="px-4 py-3">Proposta</th><th class="px-4 py-3">Eseguito</th><th class="px-4 py-3">Dopo</th><th class="px-4 py-3">Liquidità virtuale</th><th class="px-4 py-3">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($investments as $investment)
                                @php
                                    $isLatest = $latest?->is($investment) && $plan->status === 'active';
                                    $rangeLabel = match ($investment->range_status) {
                                        'virtual_liquidity' => 'Liquidità virtuale',
                                        'above_increment' => 'Oltre incremento',
                                        default => 'Nel range',
                                    };
                                @endphp
                                <tr class="align-top text-gray-700 dark:text-gray-300">
                                    <td class="px-4 py-4 font-semibold text-gray-950 dark:text-white">
                                        {{ $investment->period->format('m/Y') }}
                                        <span @class([
                                            'mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                            'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-200' => $investment->range_status === 'virtual_liquidity',
                                            'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200' => $investment->range_status === 'above_increment',
                                            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => $investment->range_status === 'in_range',
                                        ])>{{ $rangeLabel }}</span>
                                    </td>
                                    <td class="px-4 py-4 tabular-nums">€ {{ $investment->unit_price }}<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $investment->price_date->format('d/m/Y') }}</div></td>
                                    <td class="px-4 py-4 tabular-nums">{{ number_format($investment->quantity_before, 0, ',', '.') }}</td>
                                    <td class="px-4 py-4 tabular-nums">€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('portfolio_value_before')) }}</td>
                                    <td class="px-4 py-4 tabular-nums">€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('target_amount')) }}</td>
                                    <td class="px-4 py-4 tabular-nums">€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('theoretical_investment')) }}</td>
                                    <td class="px-4 py-4 tabular-nums">{{ number_format($investment->proposed_quantity, 0, ',', '.') }} q<br><span class="text-xs text-gray-500">€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('proposed_amount')) }}</span></td>
                                    <td class="px-4 py-4 tabular-nums">{{ number_format($investment->purchased_quantity, 0, ',', '.') }} q<br><span class="text-xs text-gray-500">€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('purchased_amount')) }}</span></td>
                                    <td class="px-4 py-4 tabular-nums">{{ number_format($investment->quantity_after, 0, ',', '.') }} q<br><span class="text-xs text-gray-500">€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('invested_amount_after')) }}</span></td>
                                    <td class="px-4 py-4 tabular-nums">+€ {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('virtual_liquidity_increase')) }}<div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tot. € {{ \App\Support\Decimal::formatMoney((int) $investment->getRawOriginal('virtual_liquidity_after')) }}</div></td>
                                    <td class="px-4 py-4">
                                        <details class="group">
                                            <summary class="cursor-pointer whitespace-nowrap text-xs font-semibold text-primary-700 hover:text-primary-900 dark:text-primary-300">Fotografia</summary>
                                            <div class="absolute z-10 mt-2 grid min-w-64 gap-1 rounded-xl border border-gray-200 bg-white p-3 text-xs shadow-xl dark:border-gray-700 dark:bg-gray-950">
                                                <span>Segnalazione: <strong>{{ $rangeLabel }}</strong></span>
                                                <span>Arrotondamento oltre incremento: <strong>{{ $investment->rounding_exceeds_increment ? 'Sì' : 'No' }}</strong></span>
                                                <span>Valore dopo acquisto: <strong>€ {{ \App\Support\Decimal::formatMoney(\App\Support\Decimal::value($investment->quantity_after, \App\Support\Decimal::price((string) $investment->unit_price))) }}</strong></span>
                                            </div>
                                        </details>
                                        @if ($isLatest)
                                            <div class="mt-2 flex flex-col items-start gap-1">
                                                <button type="button" class="text-xs font-semibold text-gray-700 hover:text-primary-700 dark:text-gray-300 dark:hover:text-primary-300" wire:click="$wire.mountAction('editLatestInvestment', { investment: {{ $investment->id }} })">Correggi</button>
                                                <button type="button" class="text-xs font-semibold text-rose-700 hover:text-rose-900 dark:text-rose-300" wire:click="$wire.mountAction('deleteLatestInvestment', { investment: {{ $investment->id }} })">Elimina</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
