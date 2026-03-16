<?php

use App\Enums\DirektoriumProcessingStatus;
use App\Models\DirektoriumEdition;
use App\Models\DirektoriumEntry;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $date = '';

    /** @var \Illuminate\Database\Eloquent\Collection<int, DirektoriumEntry> */
    public $entries;

    public ?int $currentEditionId = null;

    public ?string $currentEditionSourceUrl = null;

    public function mount(): void
    {
        $this->entries = collect();
    }

    #[On('open-direktorium')]
    public function open(string $date): void
    {
        $this->date = $date;
        $this->loadEntries();
    }

    private function loadEntries(): void
    {
        $edition = DirektoriumEdition::query()
            ->where('is_current', true)
            ->where('processing_status', DirektoriumProcessingStatus::Completed)
            ->first();

        if (! $edition) {
            $this->entries = collect();
            $this->currentEditionId = null;

            return;
        }

        $this->currentEditionId = $edition->id;
        $this->currentEditionSourceUrl = $edition->source_url;

        $this->entries = DirektoriumEntry::query()
            ->where('direktorium_edition_id', $edition->id)
            ->forDate($this->date)
            ->select(['id', 'entry_date', 'markdown_text', 'pdf_page_start', 'pdf_page_end'])
            ->get();
    }
};
?>

<flux:modal name="direktorium" class="w-full max-w-3xl">
    <div class="space-y-5">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">Direktórium</flux:heading>
                @if ($date)
                    <flux:text class="text-neutral-500">
                        {{ \Carbon\Carbon::parse($date)->translatedFormat('Y. F j., l') }}
                    </flux:text>
                @endif
            </div>
        </div>

        <div wire:loading class="text-center py-12">
            <flux:icon.loading class="h-12 w-12 mx-auto text-blue-600" />
        </div>

        <div wire:loading.remove>
        @if ($entries->isEmpty())
            <flux:callout color="zinc" icon="information-circle">
                <flux:callout.heading>Nincs adat erre a napra</flux:callout.heading>
                <flux:callout.text>
                    @if (!$currentEditionId)
                        Még nincs feldolgozott Direktórium-kiadás. Kérd meg az adminisztrátort, hogy töltse fel.
                    @else
                        Erre a napra nem találtunk bejegyzést a Direktóriumban.
                    @endif
                </flux:callout.text>
            </flux:callout>
        @else
            @foreach ($entries as $entry)
                @php
                    $parsed = $entry->parsedMarkdown();
                    $color = $parsed['liturgical_color'];

                    $borderColor = match ($color) {
                        'viola' => 'border-purple-500 dark:border-purple-400',
                        'fehér' => 'border-zinc-300 dark:border-zinc-100',
                        'piros' => 'border-red-500 dark:border-red-400',
                        'zöld' => 'border-green-500 dark:border-green-400',
                        'rózsaszín' => 'border-pink-500 dark:border-pink-400',
                        default => 'border-neutral-300 dark:border-neutral-600',
                    };

                    $rankLabels = [
                        'FÜ' => 'Főünnep',
                        'Ü' => 'Ünnep',
                        'E' => 'Emléknap',
                        'e' => 'Szabad emléknap',
                    ];

                    $gyLabels = [
                        'GY0' => 'Gyászmise nem mondható',
                        'GY1' => 'Temetési mise mondható',
                        'GY2' => 'Gyászmise mondható',
                    ];

                    $vLabels = [
                        'V0' => 'Votív és rituális mise nem mondható',
                        'V1' => 'Rituális mise mondható, votív mise engedéllyel',
                        'V2' => 'Votív és rituális mise mondható',
                    ];
                @endphp

                <div class="border-l-4 {{ $borderColor }} pl-4 space-y-3">
                    {{-- Title --}}
                    @if ($parsed['celebration_title'])
                        <flux:heading size="md" class="leading-snug">
                            {{ $parsed['celebration_title'] }}
                        </flux:heading>
                    @endif

                    {{-- Badge row --}}
                    @if ($parsed['rank_code'] || $parsed['funeral_mass_code'] || $parsed['votive_mass_code'] || $parsed['is_pro_populo'] || $parsed['is_penitential'] || $parsed['fast_level'] >= 2 || $parsed['zsolozsma_week'])
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($parsed['rank_code'] && isset($rankLabels[$parsed['rank_code']]))
                                <flux:badge color="amber" size="sm">{{ $rankLabels[$parsed['rank_code']] }}</flux:badge>
                            @endif
                            @if ($parsed['is_pro_populo'])
                                <flux:badge color="blue" size="sm">Pro populo</flux:badge>
                            @endif
                            @if ($parsed['zsolozsma_week'])
                                <flux:badge color="indigo" size="sm">Zsolozsma {{ $parsed['zsolozsma_week'] }}</flux:badge>
                            @endif
                            @if ($parsed['is_penitential'])
                                <flux:badge color="purple" size="sm">† Bűnbánati nap</flux:badge>
                            @endif
                            @if ($parsed['fast_level'] >= 2)
                                <flux:badge color="red" size="sm">
                                    {{ $parsed['fast_level'] === 3 ? '††† Szigorú böjt' : '†† Böjt' }}
                                </flux:badge>
                            @endif
                            @if ($parsed['funeral_mass_code'])
                                <flux:badge color="zinc" size="sm">{{ $gyLabels[$parsed['funeral_mass_code']] ?? $parsed['funeral_mass_code'] }}</flux:badge>
                            @endif
                            @if ($parsed['votive_mass_code'])
                                <flux:badge color="zinc" size="sm">{{ $vLabels[$parsed['votive_mass_code']] ?? $parsed['votive_mass_code'] }}</flux:badge>
                            @endif
                        </div>
                    @endif

                    {{-- Cleaned markdown --}}
                    @if ($parsed['cleaned_markdown'])
                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            {!! \Illuminate\Support\Str::markdown($parsed['cleaned_markdown']) !!}
                        </div>
                    @endif
                </div>

                @if ($entry->pdf_page_start)
                    <div class="border-t border-neutral-100 dark:border-neutral-800 pt-2">
                        <flux:link
                            href="{{ $currentEditionSourceUrl ?? '#' }}#page={{ $entry->pdf_page_start + 1 }}"
                            target="_blank"
                            class="text-xs inline-flex items-center gap-1 text-neutral-400 hover:text-blue-600">
                            <flux:icon name="document-text" class="h-3.5 w-3.5" variant="mini" />
                            PDF {{ $entry->pdf_page_start + 1 }}.
                            @if ($entry->pdf_page_end && $entry->pdf_page_end > $entry->pdf_page_start)
                                – {{ $entry->pdf_page_end + 1 }}.
                            @endif
                            oldal
                        </flux:link>
                    </div>
                @endif
            @endforeach
        @endif
        </div>{{-- end wire:loading.remove --}}

        <div class="flex justify-end pt-2">
            <flux:modal.close>
                <flux:button variant="ghost">Bezárás</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
