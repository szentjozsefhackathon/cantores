<?php

namespace App\Livewire\Pages;

use App\Facades\RealmContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Services\CelebrationSearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
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
        $realmId = RealmContext::getId();

        // Get music plans that have at least one of the celebrations
        $query = MusicPlan::whereHas('celebrations', function ($q) use ($celebrationIds) {
            $q->whereIn('celebrations.id', $celebrationIds);
        })
            ->with([
                'celebrations',
                'musicAssignments.music.collections',
                'musicAssignments.musicPlanSlot',
            ])
            ->withCount('celebrations');

        // Filter by realm: include plans that belong to the current realm OR have no realm
        if ($realmId !== null) {
            $query->where(function ($q) use ($realmId) {
                $q->whereNull('realm_id')
                    ->orWhere('realm_id', $realmId);
            });
        }

        // Show only published plans and user's own plans (private or published)
        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('is_published', true)
                    ->orWhere('user_id', $user->id);
            });
        } else {
            $query->where('is_published', true);
        }

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
                $primaryCollection = $music->collections->first();
                if ($primaryCollection) {
                    $collectionInfo = $primaryCollection->formatWithPivot($primaryCollection->pivot);
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
}
?>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <!-- Page header -->
    <div class="mb-10">
        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
            {{ __('Énekrend javaslatok') }}
        </flux:heading>
        <flux:text class="text-gray-600 dark:text-gray-400 mt-2 max-w-3xl">
            Kapcsolódó ünnepek alapján generált énekjavaslatok szekciók szerint. A javaslatok relevanciája a kapcsolódás erősségétől függ.
        </flux:text>
    </div>

    @if ($celebrationsWithScores->isEmpty())
        <flux:callout color="amber" icon="information-circle" class="mt-8">
            <flux:callout.heading>Nincs találat</flux:callout.heading>
            <flux:callout.text>A megadott kritériumokhoz nem található kapcsolódó ünnep. Próbálj meg más keresési feltételeket megadni.</flux:callout.text>
        </flux:callout>
    @else
        <!-- Tabs navigation -->
        <div class="mb-8 border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex flex-wrap gap-2 md:gap-0 md:space-x-8" role="tablist" aria-label="Javaslatok szekciók">
                <button
                    wire:click="$set('activeTab', 'music')"
                    role="tab"
                    aria-selected="{{ $activeTab === 'music' ? 'true' : 'false' }}"
                    aria-controls="music-panel"
                    id="music-tab"
                    @class([
                        'py-3 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap',
                        'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400' => $activeTab === 'music',
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'music',
                    ])
                >
                    <div class="flex items-center gap-2">
                        <flux:icon name="musical-note" class="h-4 w-4" />
                        <span class="hidden sm:inline">Énekjavaslatok</span>
                        <span class="sm:hidden">Énekek</span>
                        <flux:badge size="xs" color="zinc" class="ml-1">{{ count($slotMusicMap) }}</flux:badge>
                    </div>
                </button>
                <button
                    wire:click="$set('activeTab', 'celebrations')"
                    role="tab"
                    aria-selected="{{ $activeTab === 'celebrations' ? 'true' : 'false' }}"
                    aria-controls="celebrations-panel"
                    id="celebrations-tab"
                    @class([
                        'py-3 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap',
                        'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400' => $activeTab === 'celebrations',
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'celebrations',
                    ])
                >
                    <div class="flex items-center gap-2">
                        <flux:icon name="calendar" class="h-4 w-4" />
                        <span class="hidden sm:inline">Ünnepek</span>
                        <span class="sm:hidden">Ünn.</span>
                        <flux:badge size="xs" color="zinc" class="ml-1">{{ $celebrationsWithScores->count() }}</flux:badge>
                    </div>
                </button>
                <button
                    wire:click="$set('activeTab', 'plans')"
                    role="tab"
                    aria-selected="{{ $activeTab === 'plans' ? 'true' : 'false' }}"
                    aria-controls="plans-panel"
                    id="plans-tab"
                    @class([
                        'py-3 px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap',
                        'border-blue-500 text-blue-600 dark:text-blue-400 dark:border-blue-400' => $activeTab === 'plans',
                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' => $activeTab !== 'plans',
                    ])
                >
                    <div class="flex items-center gap-2">
                        <flux:icon name="folder-git-2" class="h-4 w-4" />
                        <span class="hidden sm:inline">Énekrendek</span>
                        <span class="sm:hidden">Rendek</span>
                        <flux:badge size="xs" color="zinc" class="ml-1">{{ $musicPlans->count() }}</flux:badge>
                    </div>
                </button>
            </nav>
        </div>

        <!-- Tab content -->
        <div>
            <!-- Music suggestions tab -->
            @if($activeTab === 'music')
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
            @endif

            <!-- Celebrations tab -->
            @if($activeTab === 'celebrations')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" role="tabpanel" id="celebrations-panel" aria-labelledby="celebrations-tab">
                    @foreach ($celebrationsWithScores as $item)
                        @php
                            $celebration = $item['celebration'];
                            $score = $item['score'];
                            $scoreColor = $score >= 80 ? 'green' : ($score >= 50 ? 'blue' : 'zinc');
                        @endphp
                        <flux:card class="p-5 hover:shadow-lg transition-shadow">
                            <div class="flex items-start justify-between mb-4">
                                <flux:heading size="lg" class="text-gray-900 dark:text-gray-100">
                                    {{ $celebration->name }}
                                </flux:heading>
                                <flux:badge color="{{ $scoreColor }}" size="lg" class="font-semibold">
                                    {{ $score }}%
                                </flux:badge>
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
                                    <flux:button size="sm" variant="ghost" icon="arrow-right">
                                        Megtekintés
                                    </flux:button>
                                </div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            @endif

            <!-- Music plans tab -->
            @if($activeTab === 'plans')
                <div class="space-y-5" role="tabpanel" id="plans-panel" aria-labelledby="plans-tab">
                    @foreach ($musicPlans as $plan)
                        <flux:card class="p-5 hover:shadow-lg transition-shadow">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <flux:heading size="lg" class="text-gray-900 dark:text-gray-100">
                                            {{ $plan->celebrationName ?? 'Ismeretlen ünnep' }}
                                        </flux:heading>
                                        <flux:badge color="{{ $plan->is_published ? 'green' : 'zinc' }}" size="sm">
                                            {{ $plan->is_published ? 'Közzétéve' : 'Privát' }}
                                        </flux:badge>
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="calendar" class="h-4 w-4 text-gray-500" />
                                            <span class="text-gray-700 dark:text-gray-300">
                                                {{ $plan->actual_date?->translatedFormat('Y. m. d.') ?? 'Nincs dátum' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="map-pin" class="h-4 w-4 text-gray-500" />
                                            <span class="text-gray-700 dark:text-gray-300">
                                                {{ $plan->realm?->name ?? 'Nincs realm' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="user" class="h-4 w-4 text-gray-500" />
                                            <span class="text-gray-700 dark:text-gray-300">
                                                {{ $plan->user?->display_name ?? 'Ismeretlen' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <flux:icon name="music" class="h-4 w-4 text-gray-500" />
                                            <span class="text-gray-700 dark:text-gray-300">
                                                {{ $plan->musicAssignments->count() }} ének
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <flux:button size="sm" variant="ghost" icon="eye">
                                        Megtekintés
                                    </flux:button>
                                    <flux:button size="sm" variant="primary" icon="clipboard-copy">
                                        Másolás
                                    </flux:button>
                                </div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>