<?php

use App\Facades\GenreContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Services\CelebrationSearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
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

    public function mount(array $criteria = []): void
    {
        $this->criteria = $criteria;

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
};
?>

<div>
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
