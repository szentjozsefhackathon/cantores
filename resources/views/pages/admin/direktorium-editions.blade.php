@php use App\Enums\DirektoriumProcessingStatus; @endphp
<x-pages::admin.layout :heading="__('Direktórium')" :subheading="__('PDF feltöltés és AI feldolgozás')">
    <div class="space-y-8">
        <div class="flex justify-end">
            <flux:button variant="ghost" icon="table-cells" :href="route('admin.direktorium.entries')" wire:navigate>
                Bejegyzések böngészése
            </flux:button>
        </div>

        {{-- Upload new edition --}}
        <flux:card class="space-y-4">
            <flux:heading size="md">Új kiadás feltöltése</flux:heading>

            <form wire:submit="uploadPdf" class="space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                    <flux:field>
                        <flux:label>Év</flux:label>
                        <flux:input
                            type="number"
                            wire:model="uploadYear"
                            min="2020"
                            max="2035"
                            class="w-28" />
                        <flux:error name="uploadYear" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Markdown fájl (max. 20 MB)</flux:label>
                        <flux:input
                            type="file"
                            wire:model="pdfFile"
                            accept=".md,.txt" />
                        <flux:error name="pdfFile" />
                    </flux:field>

                    <flux:field class="min-w-64">
                        <flux:label>Forrás URL (liturgia.hu)</flux:label>
                        <flux:input
                            type="url"
                            wire:model="sourceUrl"
                            placeholder="https://..." />
                        <flux:error name="sourceUrl" />
                    </flux:field>

                    <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">
                        <span wire:loading.remove>Feltöltés</span>
                        <span wire:loading>Feltöltés...</span>
                    </flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Editions list --}}
        @if ($editions->isEmpty())
            <flux:callout color="zinc">
                <flux:callout.heading>Még nincs feltöltött kiadás</flux:callout.heading>
                <flux:callout.text>Töltsd fel a Direktórium PDF-jét a fenti űrlapon.</flux:callout.text>
            </flux:callout>
        @else
            <div class="space-y-4">
                @foreach ($editions as $edition)
                    @php
                        $statusColor = match($edition->processing_status) {
                            DirektoriumProcessingStatus::Completed => 'green',
                            DirektoriumProcessingStatus::Processing => 'blue',
                            DirektoriumProcessingStatus::Failed => 'red',
                            default => 'zinc',
                        };
                        $statusLabel = match($edition->processing_status) {
                            DirektoriumProcessingStatus::Completed => 'Kész',
                            DirektoriumProcessingStatus::Processing => 'Feldolgozás alatt...',
                            DirektoriumProcessingStatus::Failed => 'Hiba',
                            default => 'Várakozik',
                        };
                    @endphp

                    <flux:card class="space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <flux:heading size="md">{{ $edition->year }}. évi kiadás</flux:heading>
                                    @if ($edition->is_current)
                                        <flux:badge color="green" size="sm">Aktív</flux:badge>
                                    @endif
                                    <flux:badge color="{{ $statusColor }}" size="sm">{{ $statusLabel }}</flux:badge>
                                </div>
                                <flux:text class="text-sm text-neutral-500">{{ $edition->original_filename }}</flux:text>

                                <div class="flex items-center gap-2 mt-1"
                                     x-data="{ url: '{{ $edition->source_url }}' }">
                                    <flux:input
                                        type="url"
                                        x-model="url"
                                        x-on:change="$wire.updateSourceUrl({{ $edition->id }}, url)"
                                        placeholder="Forrás URL (liturgia.hu)..."
                                        class="text-sm" />
                                    <a x-show="url" x-bind:href="url" target="_blank" class="text-sm shrink-0 text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                                        <flux:icon name="arrow-top-right-on-square" class="h-4 w-4" />
                                    </a>
                                </div>

                                @if ($edition->processing_status === DirektoriumProcessingStatus::Completed)
                                    <flux:text class="text-sm">
                                        {{ $edition->entries_count }} bejegyzés · {{ $edition->total_pages }} oldal
                                        @if ($edition->processing_completed_at)
                                            · Feldolgozva: {{ $edition->processing_completed_at->format('Y. m. d. H:i') }}
                                        @endif
                                    </flux:text>
                                @endif

                                @if ($edition->processing_status === DirektoriumProcessingStatus::Processing)
                                    <div class="space-y-1">
                                        <flux:text class="text-sm">
                                            {{ $edition->processed_pages }} / {{ $edition->total_pages ?? '?' }} oldal feldolgozva
                                            ({{ $edition->processingProgressPercent() }}%)
                                        </flux:text>
                                        <div class="w-full bg-neutral-200 dark:bg-neutral-700 rounded-full h-2">
                                            <div
                                                class="bg-blue-500 h-2 rounded-full transition-all"
                                                style="width: {{ $edition->processingProgressPercent() }}%">
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($edition->processing_status === DirektoriumProcessingStatus::Failed && $edition->processing_error)
                                    <flux:text class="text-sm text-red-600 dark:text-red-400">
                                        Hiba: {{ Str::limit($edition->processing_error, 200) }}
                                    </flux:text>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if (in_array($edition->processing_status, [DirektoriumProcessingStatus::Pending, DirektoriumProcessingStatus::Failed, DirektoriumProcessingStatus::Completed]))
                                    <div
                                        x-data="{
                                            start: 1,
                                            end: '',
                                            confirmMessage: @js($edition->processing_status === DirektoriumProcessingStatus::Completed
                                                ? 'Újrafeldolgozod a megadott oldalakat? Ez API-költséggel jár.'
                                                : 'Elindítod az AI feldolgozást? Ez eltarthat néhány percig és API-költséggel jár.')
                                        }"
                                        class="flex flex-wrap items-center gap-2">
                                        <flux:input type="number" x-model="start" min="1" placeholder="Kezdő oldal" class="w-28" size="sm" />
                                        <flux:input type="number" x-model="end" min="1" placeholder="Utolsó oldal" class="w-28" size="sm" />
                                        <flux:button
                                            x-on:click="if (window.confirm(confirmMessage)) { $wire.process({{ $edition->id }}, Number(start), end || null) }"
                                            variant="{{ $edition->processing_status === DirektoriumProcessingStatus::Completed ? 'ghost' : 'primary' }}"
                                            size="sm"
                                            icon="{{ $edition->processing_status === DirektoriumProcessingStatus::Completed ? 'arrow-path' : 'cpu-chip' }}">
                                            {{ $edition->processing_status === DirektoriumProcessingStatus::Completed ? 'Újrafeldolgozás' : 'Feldolgozás indítása' }}
                                        </flux:button>
                                    </div>
                                @endif

                                @if ($edition->processing_status === DirektoriumProcessingStatus::Processing)
                                    <flux:button
                                        wire:click="markAsFailed({{ $edition->id }})"
                                        wire:confirm="A beragadt feldolgozást lezárod hibásként? Ez nem állítja le a queue workert, csak feloldja ezt a kiadást újrafeldolgozáshoz."
                                        variant="ghost"
                                        size="sm"
                                        icon="x-circle">
                                        Beragadt job lezárása
                                    </flux:button>
                                @endif

                                @if (!$edition->is_current && $edition->processing_status === DirektoriumProcessingStatus::Completed)
                                    <flux:button
                                        wire:click="markAsCurrent({{ $edition->id }})"
                                        wire:confirm="Ezt jelölöd aktívnak? A többi kiadás inaktív lesz."
                                        variant="filled"
                                        size="sm"
                                        icon="check-circle">
                                        Beállítás aktívnak
                                    </flux:button>
                                @endif

                                @if ($edition->entries_count > 0)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="table-cells"
                                        :href="route('admin.direktorium.entries', ['editionFilter' => $edition->id])"
                                        wire:navigate>
                                        Bejegyzések
                                    </flux:button>
                                @endif

                                <flux:button
                                    wire:click="delete({{ $edition->id }})"
                                    wire:confirm="Törlöd ezt a kiadást? Ez visszafordíthatatlan, a bejegyzések és a PDF is törlődik."
                                    variant="danger"
                                    size="sm"
                                    icon="trash">
                                    Törlés
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>
</x-pages::admin.layout>
