<?php

namespace App\Livewire\Pages;

use App\Facades\GenreContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Services\CelebrationSearchService;
use App\Services\LiturgicalInfoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts::app.main')] class extends Component
{
    /** @var array<string, mixed> */
    public array $criteria = [];

    /** @var Collection<int, array{celebration: Celebration, score: int}> */
    public Collection $celebrationsWithScores;

    /** @var Collection<int, MusicPlan> */
    public Collection $musicPlans;

    /** @var array<string, array<int, array{music: \App\Models\Music, celebration_score: int, music_sequence: int, collection_info: ?string}>> */
    public array $slotMusicMap = [];

    public string $activeTab = 'music';

    public string $activePartTab = 'part-0';

    public string $activePart2Tab = 'part2-0';

    /** @var array<string, mixed>|null */
    public ?array $celebrationDetails = null;

    public function mount(): void
    {
        $this->criteria = request()->query();

        $service = app(CelebrationSearchService::class);
        $related = $service->findRelated($this->criteria);

        // Store celebrations with scores
        $this->celebrationsWithScores = $related->map(fn (Celebration $celebration) => [
            'celebration' => $celebration,
            'score' => $celebration->score,
        ]);

        // Collect all music plans for these celebrations
        $celebrationIds = $related->pluck('id')->toArray();
        $this->musicPlans = $this->fetchMusicPlans($celebrationIds);

        // Aggregate music selections by slot
        $this->slotMusicMap = $this->aggregateMusicBySlot();

        // Fetch celebration details from external API for the first celebration (highest score)
        $this->fetchCelebrationDetails();
    }

    /**
     * Fetch celebration details from the external API using the caching service.
     */
    protected function fetchCelebrationDetails(): void
    {
        if ($this->celebrationsWithScores->isEmpty()) {
            return;
        }

        // Get the first celebration (highest score)
        $firstCelebration = $this->celebrationsWithScores->first()['celebration'];
        $date = $firstCelebration->actual_date->format('Y-m-d');

        $service = app(LiturgicalInfoService::class);
        $celebrationData = $service->findCelebration(
            $date,
            $firstCelebration->name,
            $firstCelebration->actual_date->format('Y-m-d')
        );

        $this->celebrationDetails = $celebrationData;
    }

    /**
     * Handle genre change event.
     */
    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        // Reload music plans and slot music map when genre changes
        $celebrationIds = $this->celebrationsWithScores->pluck('celebration.id')->toArray();
        $this->musicPlans = $this->fetchMusicPlans($celebrationIds);
        $this->slotMusicMap = $this->aggregateMusicBySlot();
    }

    /**
     * Open suggestions page for a specific celebration.
     */
    public function openCelebrationSuggestions(int $celebrationId): void
    {
        $celebration = Celebration::find($celebrationId);
        if (!$celebration) {
            return;
        }

        // Build criteria from the celebration model (same as openSuggestions in liturgical-info)
        $criteria = [
            'name' => $celebration->name,
            'season' => $celebration->season,
            'week' => $celebration->week,
            'day' => $celebration->day,
            'readings_code' => $celebration->readings_code,
            'year_letter' => $celebration->year_letter,
            'year_parity' => $celebration->year_parity,
        ];

        // Remove null values
        $criteria = array_filter($criteria, fn ($value) => $value !== null);

        // Redirect to suggestions page with new criteria
        $this->redirectRoute('suggestions', $criteria);
    }

    /**
     * Fetch music plans associated with the given celebration IDs.
     *
     * @param  array<int>  $celebrationIds
     * @return Collection<int, MusicPlan>
     */
    protected function fetchMusicPlans(array $celebrationIds): Collection
    {
        $user = Auth::user();
        $genreId = GenreContext::getId();

        // Get music plans that have at least one of the celebrations
        $query = MusicPlan::whereHas('celebrations', function ($q) use ($celebrationIds) {
            $q->whereIn('celebrations.id', $celebrationIds);
        })
            ->with([
                'celebrations',
                'musicAssignments.music' => fn ($q) => $q->visibleTo($user),
                'musicAssignments.music.collections' => fn ($q) => $q->visibleTo($user),
                'musicAssignments.musicPlanSlot' => fn ($q) => $q->visibleToUser($user),
            ])
            ->withCount('celebrations');

        // Filter by genre: include plans that belong to the current genre OR have no genre
        if ($genreId !== null) {
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }

        // Show only published plans and user's own plans (private or published)
        $query->visibleTo($user);

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Aggregate music selections by slot, sorted according to requirements.
     *
     * @return array<string, array<int, array{music: \App\Models\Music, celebration_score: int, music_sequence: int, collection_info: ?string}>>
     */
    protected function aggregateMusicBySlot(): array
    {
        $slotMap = [];

        foreach ($this->musicPlans as $musicPlan) {
            // Determine the celebration score for this plan (highest score among its celebrations?)
            // We'll compute the max score of celebrations that are in our list.
            $planCelebrationIds = $musicPlan->celebrations->pluck('id')->toArray();
            $maxScore = 0;
            foreach ($this->celebrationsWithScores as $item) {
                if (in_array($item['celebration']->id, $planCelebrationIds)) {
                    $maxScore = max($maxScore, $item['score']);
                }
            }

            // Iterate through assignments
            foreach ($musicPlan->musicAssignments as $assignment) {
                $slot = $assignment->musicPlanSlot;
                if (! $slot) {
                    continue;
                }

                $slotKey = $slot->id;
                $slotName = $slot->name;
                $priority = $slot->priority;

                // Use a composite key for sorting: priority + slot name
                $sortKey = sprintf('%04d-%s', $priority, $slotName);

                if (! isset($slotMap[$sortKey])) {
                    $slotMap[$sortKey] = [
                        'slot' => $slot,
                        'musics' => [],
                        'music_ids' => [],
                    ];
                }

                // Get primary collection info
                $collectionInfo = null;
                $music = $assignment->music;
                if ($music) {
                    $primaryCollection = $music->collections->first();
                    if ($primaryCollection) {
                        $collectionInfo = $primaryCollection->formatWithPivot($primaryCollection->pivot);
                    }
                }

                $musicId = $music->id;
                $existingIndex = $slotMap[$sortKey]['music_ids'][$musicId] ?? null;

                if ($existingIndex !== null) {
                    // Duplicate music in same slot: keep the entry with higher celebration score
                    // If scores equal, keep the one with lower music_sequence
                    $existing = &$slotMap[$sortKey]['musics'][$existingIndex];
                    if ($maxScore > $existing['celebration_score'] ||
                        ($maxScore === $existing['celebration_score'] && ($assignment->music_sequence ?? 0) < $existing['music_sequence'])) {
                        // Replace with this better entry
                        $existing = [
                            'music' => $music,
                            'celebration_score' => $maxScore,
                            'music_sequence' => $assignment->music_sequence ?? 0,
                            'collection_info' => $collectionInfo,
                        ];
                    }
                    // else keep existing
                } else {
                    // New music for this slot
                    $slotMap[$sortKey]['music_ids'][$musicId] = count($slotMap[$sortKey]['musics']);
                    $slotMap[$sortKey]['musics'][] = [
                        'music' => $music,
                        'celebration_score' => $maxScore,
                        'music_sequence' => $assignment->music_sequence ?? 0,
                        'collection_info' => $collectionInfo,
                    ];
                }
            }
        }

        // Sort slots by priority then slot name (already encoded in sortKey)
        ksort($slotMap);

        // For each slot, sort musics first by celebration score descending, then by music_sequence ascending
        foreach ($slotMap as &$slotData) {
            // Remove the temporary music_ids array
            unset($slotData['music_ids']);
            usort($slotData['musics'], function ($a, $b) {
                if ($a['celebration_score'] !== $b['celebration_score']) {
                    return $b['celebration_score'] <=> $a['celebration_score']; // descending
                }

                return $a['music_sequence'] <=> $b['music_sequence']; // ascending
            });
        }

        // Return map with slot name as key for easy display
        $result = [];
        foreach ($slotMap as $slotData) {
            $slot = $slotData['slot'];
            $result[$slot->name] = $slotData['musics'];
        }

        return $result;
    }

    protected function sanitize($text): string
    {
        return strip_tags($text, '<b><br><sup><small><i><em><strong>');
    }
}
?>

<div class="max-w-7xl mx-auto py-2 px-2 sm:px-6 lg:px-8">
    <!-- Page header -->
    <div class="mb-2">
        <flux:heading size="xl">{{ __('Énekrend javaslatok') }}</flux:heading>
        <flux:text>
            Kapcsolódó ünnepek alapján generált énekjavaslatok szekciók szerint. A javaslatok relevanciája a kapcsolódás erősségétől függ.
        </flux:text>
    </div>

    <!-- Celebration details section -->
    @if ($celebrationDetails)
        <div class="mb-4">
            <flux:card class="p-4 pb-0">
                <div class="flex items-center gap-2 mb-2">
                    <flux:heading size="lg">
                        {{ $celebrationDetails['name'] ?? $celebrationDetails['title'] ?? 'Ünnep adatai' }}
                    </flux:heading>
                    <flux:badge color="blue" size="lg">
                        {{ \Carbon\Carbon::parse($celebrationDetails['dateISO'] ?? '')->translatedFormat('Y. F j.') }}
                    </flux:badge>
                </div>

                <!-- Display parts as tabs -->
                @if (isset($celebrationDetails['parts']) && is_array($celebrationDetails['parts']))
                    <x-mary-tabs wire:model="activePartTab">
                        @foreach ($celebrationDetails['parts'] as $partIndex => $part)
                            <x-mary-tab name="part-{{ $partIndex }}" label="{{ $part['short_title'] ?? 'Rész ' . ($partIndex + 1) }}">
                                <div class="rounded-2xl border border-zinc-200 bg-amber-50/80 dark:bg-zinc-900 p-4 dark:border-zinc-800 p-2 font-serif shadow-lg">
                                    <div class="grid grid-cols-1 gap-2 text-sm ">
                                        <div>
                                            <flux:heading class="inline" >{{ $part['ref'] ?? '' }}</flux:heading>
                                            <flux:text class="inline">{!! $this->sanitize($part['teaser'] ?? '') !!}</flux:text>
                                        </div>
                                        <div>{{ $part['title'] ?? '' }}</div>
                                        <div>{!! $this->sanitize($part['text'] ?? '') !!}</div>
                                        <div>{{ $part['ending'] ?? '' }}</div>
                                    </div>
                                </div>
                            </x-mary-tab>
                        @endforeach
                    </x-mary-tabs>
                @else
                    <flux:callout color="zinc" icon="information-circle">
                        <flux:callout.heading>Nincs részletes adat</flux:callout.heading>
                        <flux:callout.text>Ehhez az ünnephez nem található részletes olvasmány adat.</flux:callout.text>
                    </flux:callout>
                @endif

                <!-- Display parts2 as tabs -->
                @if (isset($celebrationDetails['parts2']) && is_array($celebrationDetails['parts2']))
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <flux:heading size="lg" class="mb-4">
                            {{ $celebrationDetails['parts2cause'] ?? 'Másodlagos olvasmányok' }}
                        </flux:heading>
                        <x-mary-tabs wire:model="activePart2Tab" class="mb-6">
                            @foreach ($celebrationDetails['parts2'] as $partIndex => $part)
                                <x-mary-tab name="part2-{{ $partIndex }}" label="{{ $part['short_title'] ?? 'Rész ' . ($partIndex + 1) }}">
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 mt-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                            @foreach ($part as $key => $value)
                                                @if (!is_array($value) && $key !== 'short_title')
                                                    <div class="flex flex-col">
                                                        <span class="font-medium text-gray-700 dark:text-gray-300 capitalize">{{ $key }}</span>
                                                        <span class="text-gray-900 dark:text-gray-100 mt-1">{{ $value }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </x-mary-tab>
                            @endforeach
                        </x-mary-tabs>
                    </div>
                @endif
            </flux:card>
        </div>
    @endif

    @if ($celebrationsWithScores->isEmpty())
        <flux:callout color="amber" icon="information-circle" class="mt-8">
            <flux:callout.heading>Nincs találat</flux:callout.heading>
            <flux:callout.text>A megadott kritériumokhoz nem található kapcsolódó ünnep. Próbálj meg más keresési feltételeket megadni.</flux:callout.text>
        </flux:callout>
    @else
        <!-- Tabs navigation with mary-ui -->
        <x-mary-tabs wire:model="activeTab" class="mb-8">
            <x-mary-tab name="music" icon="o-musical-note" label="Énekjavaslatok ({{ count($slotMusicMap) }})">
                <div class="space-y-10" role="tabpanel" id="music-panel" aria-labelledby="music-tab">
                    @forelse ($slotMusicMap as $slotName => $musics)
                        <div class="relative">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex flex-row items-center gap-3">
                                    <flux:heading size="lg" class="text-gray-900 dark:text-gray-100">{{ $slotName }}</flux:heading>
                                    <flux:text class="text-gray-600 dark:text-gray-400">
                                        ({{ count($musics) }} javaslat)
                                    </flux:text>
                                </div>
                                <flux:badge color="blue" size="lg" class="font-semibold">
                                    {{ $loop->iteration }}/{{ count($slotMusicMap) }}
                                </flux:badge>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5">
                                @foreach ($musics as $musicItem)
                                    @php
                                        $music = $musicItem['music'];
                                        $score = $musicItem['celebration_score'];
                                        $sequence = $musicItem['music_sequence'];
                                        $collectionInfo = $musicItem['collection_info'];
                                    @endphp
                                    <livewire:music-card :music="$music" />
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <flux:callout color="zinc" icon="information-circle">
                            <flux:callout.heading>Nincs énekjavaslat</flux:callout.heading>
                            <flux:callout.text>Ehhez a szekcióhoz még nem tartoznak énekjavaslatok.</flux:callout.text>
                        </flux:callout>
                    @endforelse
                </div>
            </x-mary-tab>

            <x-mary-tab name="celebrations" icon="o-calendar" label="Ünnepek ({{ $celebrationsWithScores->count() }})">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" role="tabpanel" id="celebrations-panel" aria-labelledby="celebrations-tab">
                    @foreach ($celebrationsWithScores as $item)
                        @php
                            $celebration = $item['celebration'];
                            $score = $item['score'];
                            $starCount = $score <= 5 ? 1 : ($score <= 10 ? 2 : 3);
                            $starColor = $score <= 5 ? 'text-zinc-400' : ($score <= 10 ? 'text-blue-500' : 'text-blue-600');
                        @endphp
                        <flux:card class="p-5 hover:shadow-lg transition-shadow">
                            <div class="flex items-start justify-between mb-4">
                                <flux:heading size="lg" class="text-gray-900 dark:text-gray-100">
                                    {{ $celebration->name }}
                                </flux:heading>
                                <div class="flex items-center gap-1">
                                    @for ($i = 0; $i < $starCount; $i++)
                                        <flux:icon name="star" class="h-5 w-5 {{ $starColor }} fill-current" />
                                    @endfor
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center gap-3 text-sm">
                                    <flux:icon name="calendar" class="h-4 w-4 text-gray-500" />
                                    <span class="text-gray-700 dark:text-gray-300">
                                        {{ $celebration->actual_date->translatedFormat('Y. F j.') }}
                                    </span>
                                </div>
                                @if($celebration->liturgical_year)
                                    <div class="flex items-center gap-3 text-sm">
                                        <flux:icon name="book-open-text" class="h-4 w-4 text-gray-500" />
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ $celebration->liturgical_year }}
                                        </span>
                                    </div>
                                @endif
                                @if($celebration->year_letter)
                                    <div class="flex items-center gap-3 text-sm">
                                        <flux:icon name="type" class="h-4 w-4 text-gray-500" />
                                        <span class="text-gray-700 dark:text-gray-300">
                                            {{ $celebration->year_letter }}
                                            @if($celebration->year_parity)
                                                ({{ $celebration->year_parity }})
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
                                <div class="flex justify-between items-center">
                                    <flux:text class="text-xs text-gray-500">
                                        {{ $celebration->musicPlans()->count() }} énekrend
                                    </flux:text>
                                    <flux:button size="sm" variant="ghost" icon="arrow-right" wire:click="openCelebrationSuggestions({{ $celebration->id }})">
                                        Megtekintés
                                    </flux:button>
                                </div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </x-mary-tab>

            <x-mary-tab name="plans" icon="o-folder" label="Énekrendek ({{ $musicPlans->count() }})">
                <div class="space-y-5" role="tabpanel" id="plans-panel" aria-labelledby="plans-tab">
                    @foreach ($musicPlans as $plan)
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex-1">
                                <livewire:music-plan-card-extended :musicPlan="$plan" class="max-w-full" :showOpenButton="true"/>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-mary-tab>
        </x-mary-tabs>
    @endif
</div>