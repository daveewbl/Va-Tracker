<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">Anagrafica condivisa</p>
                    <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">Un catalogo, quotazioni sempre aggiornate</h2>
                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">Ogni titolo può essere usato in più PAC. La quotazione è condivisa; lo storico degli acquisti resta legato al singolo piano.</p>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-800">
                    <span class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $instruments->count() }}</span>
                    <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">titoli</span>
                </div>
            </div>
        </section>

        @if ($instruments->isEmpty())
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-chart-bar" class="size-6" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-gray-950 dark:text-white">Il catalogo è ancora vuoto</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600 dark:text-gray-400">Aggiungi il primo titolo per poter aprire un PAC e registrare le quotazioni.</p>
                <div class="mt-5"><x-filament::button icon="heroicon-o-plus" wire:click="$wire.mountAction('createInstrument')">Aggiungi un titolo</x-filament::button></div>
            </section>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($instruments as $instrument)
                    <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-primary-50 px-2.5 py-1 font-mono text-sm font-semibold tracking-wide text-primary-800 dark:bg-primary-950 dark:text-primary-200">{{ $instrument->ticker }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $instrument->plans_count }} {{ $instrument->plans_count === 1 ? 'PAC collegato' : 'PAC collegati' }}</span>
                                </div>
                                <h3 class="mt-3 truncate text-lg font-semibold text-gray-950 dark:text-white">{{ $instrument->name }}</h3>
                            </div>
                            <div class="shrink-0 text-left sm:text-right">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ultima quotazione</p>
                                @if ($instrument->latest_price !== null)
                                    <p class="mt-1 text-xl font-semibold tabular-nums text-gray-950 dark:text-white">€ {{ $instrument->latest_price }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $instrument->latest_price_date?->format('d/m/Y') ?? 'Data non disponibile' }}</p>
                                @else
                                    <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Da inserire</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-pencil-square" wire:click="$wire.mountAction('editInstrument', { instrument: {{ $instrument->id }} })">Modifica titolo</x-filament::button>
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-trending-up" wire:click="$wire.mountAction('updateQuote', { instrument: {{ $instrument->id }} })">Aggiorna prezzo</x-filament::button>
                            @if ($instrument->latest_price !== null)
                                <x-filament::button size="sm" color="warning" icon="heroicon-o-pencil" wire:click="$wire.mountAction('correctQuote', { instrument: {{ $instrument->id }} })">Correggi quotazione</x-filament::button>
                            @endif
                            @if ($instrument->plans_count === 0)
                                <x-filament::button size="sm" color="danger" icon="heroicon-o-trash" wire:click="$wire.mountAction('deleteInstrument', { instrument: {{ $instrument->id }} })">Elimina</x-filament::button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
