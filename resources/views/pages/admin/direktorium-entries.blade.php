@php
    $rankLabels = [
        'FÜ' => 'Főünnep',
        'Ü' => 'Ünnep',
        'E' => 'Emléknap',
        'e' => 'Emléknap',
    ];
    $gyLabels = [
        'GY0' => 'Gyászmise nem mondható',
        'GY1' => 'Temetésnél mondható gyászmise',
        'GY2' => 'Bármely gyászmise mondható',
    ];
    $vLabels = [
        'V0' => 'Votív mise nem mondható',
        'V1' => 'Votív mise ordinárius engedélyével',
        'V2' => 'Votív mise mondható',
    ];
@endphp

<x-pages::admin.layout :heading="__('Direktórium bejegyzések')" :subheading="__('Az összes feldolgozott bejegyzés böngészése táblázatban')">
    <div class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="grid gap-4 md:grid-cols-3 xl:flex-1">
                <flux:field>
                    <flux:label>Keresés</flux:label>
                    <flux:input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cím, szöveg vagy fájlnév..." />
                </flux:field>

                <flux:field>
                    <flux:label>Dátum</flux:label>
                    <flux:input type="date" wire:model.live="entryDate" />
                </flux:field>

                <flux:field>
                    <flux:label>Kiadás</flux:label>
                    <flux:select wire:model.live="editionFilter">
                        <option value="">Összes kiadás</option>
                        @foreach ($editions as $edition)
                            <option value="{{ $edition->id }}">
                                {{ $edition->year }}@if ($edition->is_current) · aktív @endif
                            </option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="ghost" icon="x-mark" wire:click="resetFilters">
                    Szűrők törlése
                </flux:button>
                <flux:button variant="ghost" icon="arrow-left" :href="route('admin.direktorium')" wire:navigate>
                    Kiadások
                </flux:button>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Dátum</flux:table.column>
                <flux:table.column>Ünnep</flux:table.column>
                <flux:table.column>Részletek</flux:table.column>
                <flux:table.column>Oldalak</flux:table.column>
                <flux:table.column>Kiadás</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($entries as $entry)
                    @php($parsed = $entry->parsedMarkdown())
                    @php($previewText = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($parsed['cleaned_markdown'] ?: $entry->markdown_text), 50))

                    <flux:table.row wire:key="direktorium-entry-{{ $entry->id }}">
                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="font-medium">{{ $entry->entry_date->format('Y. m. d.') }}</div>
                                <div class="text-sm text-zinc-500">{{ $entry->entry_date->translatedFormat('l') }}</div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2 max-w-md">
                                <div class="font-medium leading-snug">
                                    {{ $parsed['celebration_title'] ?: 'Nincs külön címsor felismerve' }}
                                </div>

                                <div class="flex flex-wrap gap-1.5">
                                    @if ($parsed['liturgical_color'])
                                        <flux:badge color="zinc" size="sm">{{ ucfirst($parsed['liturgical_color']) }}</flux:badge>
                                    @endif
                                    @if ($parsed['rank_code'] && isset($rankLabels[$parsed['rank_code']]))
                                        <flux:badge color="amber" size="sm">{{ $rankLabels[$parsed['rank_code']] }}</flux:badge>
                                    @endif
                                    @if ($parsed['is_pro_populo'])
                                        <flux:badge color="blue" size="sm">Pro populo</flux:badge>
                                    @endif
                                    @if ($parsed['is_penitential'])
                                        <flux:badge color="purple" size="sm">Bűnbánati nap</flux:badge>
                                    @endif
                                    @if ($parsed['fast_level'] >= 2)
                                        <flux:badge color="red" size="sm">
                                            {{ $parsed['fast_level'] === 3 ? 'Szigorú böjt' : 'Böjt' }}
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2 max-w-xl">
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($parsed['funeral_mass_code'])
                                        <flux:badge color="zinc" size="sm">{{ $gyLabels[$parsed['funeral_mass_code']] ?? $parsed['funeral_mass_code'] }}</flux:badge>
                                    @endif
                                    @if ($parsed['votive_mass_code'])
                                        <flux:badge color="zinc" size="sm">{{ $vLabels[$parsed['votive_mass_code']] ?? $parsed['votive_mass_code'] }}</flux:badge>
                                    @endif
                                    @if ($parsed['zsolozsma_week'])
                                        <flux:badge color="indigo" size="sm">Zsolozsma {{ $parsed['zsolozsma_week'] }}</flux:badge>
                                    @endif
                                </div>

                                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $previewText }}
                                </p>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-2">
                                @if ($entry->pdf_page_start)
                                    <div class="font-medium">
                                        {{ $entry->pdf_page_start }}@if ($entry->pdf_page_end && $entry->pdf_page_end !== $entry->pdf_page_start)–{{ $entry->pdf_page_end }}@endif.
                                        oldal
                                    </div>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="arrow-top-right-on-square"
                                        :href="route('direktorium.page', ['edition' => $entry->edition, 'page' => $entry->pdf_page_start])"
                                        target="_blank">
                                        Megnyitás
                                    </flux:button>
                                @else
                                    <span class="text-sm text-zinc-500">Nincs oldaladat</span>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ $entry->edition->year }}</span>
                                    @if ($entry->edition->is_current)
                                        <flux:badge color="green" size="sm">Aktív</flux:badge>
                                    @endif
                                </div>
                                <div class="text-sm text-zinc-500">
                                    {{ $entry->edition->original_filename }}
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center">
                            <div class="flex flex-col items-center gap-2 text-zinc-500">
                                <flux:icon name="table-cells" class="h-10 w-10 opacity-50" />
                                <div class="text-lg font-medium text-zinc-700 dark:text-zinc-200">Nincs megjeleníthető bejegyzés</div>
                                <div class="text-sm">
                                    Módosítsd a szűrőket, vagy dolgozz fel egy Direktórium kiadást az admin felületen.
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        @if ($entries->hasPages())
            <div>
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</x-pages::admin.layout>