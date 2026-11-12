<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Capitale investito</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight">{{ str_replace('.', ',', \App\Support\Decimal::formatMoney($totalInvested)) }} €</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Posizioni attive, inclusi i saldi di apertura.</p>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Valore dei titoli{{ $missingPrices ? ' · parziale' : '' }}</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight">{{ str_replace('.', ',', \App\Support\Decimal::formatMoney($totalValue)) }} €</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Alle quotazioni conservate nel catalogo.</p>
        </x-filament::section>
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Liquidità virtuale</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight">{{ str_replace('.', ',', \App\Support\Decimal::formatMoney($totalVirtual)) }} €</div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Contatore separato: non è denaro disponibile.</p>
        </x-filament::section>
    </div>

    @if ($missingPrices)
        <div role="status" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-400/10 dark:text-amber-200">
            Valore parziale: {{ $missingPrices }} PAC con quote positive senza quotazione. Sono esclusi dal totale dei valori calcolabili.
            <x-filament::link :href="\App\Filament\Pages\Instruments::getUrl()">Aggiorna le quotazioni</x-filament::link>
        </div>
    @endif

    <x-filament::section>
        <x-slot name="heading">I tuoi PAC attivi</x-slot>
        <x-slot name="description">Situazione di {{ $currentPeriod }}. I mesi senza rilevazione rimangono non rilevati.</x-slot>
        @if (count($rows))
            <div class="grid gap-4">
                @foreach ($rows as $row)
                    @php($plan = $row['plan'])
                    <article wire:key="plan-{{ $plan->id }}" class="rounded-xl border border-gray-200 p-5 dark:border-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold"><a class="hover:underline" href="{{ \App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->id]) }}">{{ $plan->instrument->ticker }} <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $plan->instrument->name }}</span></a></h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Da {{ $plan->start_period->format('m/Y') }} · {{ str_replace('.', ',', \App\Support\Decimal::formatMoney((int) $plan->getRawOriginal('monthly_increment'))) }} € al mese</p>
                            </div>
                            <x-filament::badge :color="$row['eligible'] ? 'warning' : 'gray'">{{ $row['status'] }}</x-filament::badge>
                        </div>
                        <dl class="mt-5 grid grid-cols-2 gap-5 lg:grid-cols-4">
                            <div><dt class="text-sm text-gray-500 dark:text-gray-400">Quote possedute</dt><dd class="mt-1 font-semibold">{{ $row['quantity'] }}</dd></div>
                            <div><dt class="text-sm text-gray-500 dark:text-gray-400">Capitale investito</dt><dd class="mt-1 font-semibold">{{ str_replace('.', ',', \App\Support\Decimal::formatMoney($row['capital'])) }} €</dd></div>
                            <div><dt class="text-sm text-gray-500 dark:text-gray-400">Valore corrente</dt><dd class="mt-1 font-semibold">{{ $row['valuation'] === null ? 'Non disponibile' : str_replace('.', ',', \App\Support\Decimal::formatMoney($row['valuation'])) . ' €' }}</dd></div>
                            <div><dt class="text-sm text-gray-500 dark:text-gray-400">Obiettivo · {{ $currentPeriod }}</dt><dd class="mt-1 font-semibold">{{ $row['target'] === null ? 'Non ancora maturato' : str_replace('.', ',', \App\Support\Decimal::formatMoney($row['target'])) . ' €' }}</dd></div>
                        </dl>
                        <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-gray-100 pt-4 dark:border-white/10">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <p>Ultima rilevazione: {{ $row['last'] ? substr($row['last'], 5, 2) . '/' . substr($row['last'], 0, 4) : 'nessuna' }}</p>
                                <p>Quotazione: {{ $plan->instrument->latest_price === null ? 'non disponibile' : str_replace('.', ',', $plan->instrument->latest_price) . ' € · ' . $plan->instrument->latest_price_date->format('d/m/Y') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <x-filament::button color="gray" tag="a" :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->id])">Dettaglio</x-filament::button>
                                <x-filament::button tag="a" :href="\App\Filament\Pages\PlanDetail::getUrl(['plan' => $plan->id, 'record' => 1])">Registra rilevazione</x-filament::button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="py-10 text-center">
                <h2 class="text-lg font-semibold">{{ $hasClosedPlans ? 'Nessun PAC attivo' : 'Il tuo primo PAC parte da qui' }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">Crea un piano da zero o inserisci la situazione di apertura di un PAC già avviato.</p>
                <div class="mt-5"><x-filament::button tag="a" :href="\App\Filament\Pages\Plans::getUrl(['create' => 1])">Crea un PAC</x-filament::button></div>
            </div>
        @endif
        <div class="mt-5"><x-filament::link :href="\App\Filament\Pages\Plans::getUrl()">Tutti i PAC e storico dei piani chiusi →</x-filament::link></div>
    </x-filament::section>
</x-filament-panels::page>
