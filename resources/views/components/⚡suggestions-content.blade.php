<?php

use App\Facades\GenreContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Services\CelebrationSearchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
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

    public function boot(): void
    {
        if (! isset($this->celebrationsWithScores)) {
            $this->celebrationsWithScores = collect();
        }

        if (! isset($this->musicPlans)) {
            $this->musicPlans = collect();
        }
    }

    public ?int $musicPlanId = null;

    public function mount(array $criteria = [], ?int $musicPlanId = null): void
    {
        $this->criteria = $criteria;
        $this->musicPlanId = $musicPlanId;

        $service = app(CelebrationSearchService::class);
        $related = $service->findRelated($this->criteria);

        // Store celebrations with scores
        $this->celebrationsWithScores = $related->map(fn(Celebration $celebration) => [
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
        $query = MusicPlan::whereIn('celebration_id', $celebrationIds)
            ->with([
                'celebration',
                'musicAssignments.music' => fn($q) => $q->visibleTo($user),
                'musicAssignments.music.collections' => fn($q) => $q->visibleTo($user),
                'musicAssignments.musicPlanSlotPlan.musicPlanSlot' => fn($q) => $q->visibleToUser($user),
                'musicAssignments.scopes',
            ]);

        // Filter by genre: include plans that belong to the current genre OR have no genre
        if ($genreId !== null) {
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }

        // Exclude the current music plan if we're editing one
        if ($this->musicPlanId !== null) {
            $query->where('id', '!=', $this->musicPlanId);
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
        $genreId = GenreContext::getId();

        foreach ($this->musicPlans as $musicPlan) {
            // Determine the celebration score for this plan.
            $maxScore = 0;
            $relatedCelebration = null;
            foreach ($this->celebrationsWithScores as $item) {
                if ($item['celebration']->id === $musicPlan->celebration_id) {
                    $maxScore = max($maxScore, $item['score']);
                    $relatedCelebration = $item['celebration'];
                }
            }

            // Iterate through assignments
            foreach ($musicPlan->musicAssignments as $assignment) {
                $slot = $assignment->musicPlanSlotPlan?->musicPlanSlot;
                if (! $slot) {
                    continue;
                }

                // Get primary collection info
                $collectionInfo = null;
                $music = $assignment->music;
                if (! $music) {
                    continue;
                }

                // Filter music by current genre: only include if it has the current genre or has no genres
                if ($genreId !== null) {
                    $musicGenreIds = $music->genres->pluck('id')->toArray();
                    if (! empty($musicGenreIds) && ! in_array($genreId, $musicGenreIds)) {
                        continue;
                    }
                }

                $primaryCollection = $music->collections->first();
                if ($primaryCollection) {
                    $collectionInfo = $primaryCollection->formatWithPivot($primaryCollection->pivot);
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

                $musicId = $music->id;
                $existingIndex = $slotMap[$sortKey]['music_ids'][$musicId] ?? null;

                if ($existingIndex !== null) {
                    // Duplicate music in same slot: keep the entry with higher celebration score
                    // If scores equal, keep the one with lower music_sequence
                    $existing = &$slotMap[$sortKey]['musics'][$existingIndex];
                    if (
                        $maxScore > $existing['celebration_score'] ||
                        ($maxScore === $existing['celebration_score'] && ($assignment->music_sequence ?? 0) < $existing['music_sequence'])
                    ) {
                        // Replace with this better entry
                        $existing = [
                            'music' => $music,
                            'celebration_score' => $maxScore,
                            'music_sequence' => $assignment->music_sequence ?? 0,
                            'collection_info' => $collectionInfo,
                            'celebration' => $relatedCelebration,
                            'scope_label' => $assignment->scope_label,
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
                        'celebration' => $relatedCelebration,
                        'scope_label' => $assignment->scope_label,
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
        unset($slotData); // Break the reference to avoid corrupting the last element in subsequent loops

        // Return map with slot name as key for easy display
        $result = [];
        foreach ($slotMap as $slotData) {
            $slot = $slotData['slot'];
            $result[$slot->name] = [
                'slot' => $slot,
                'musics' => $slotData['musics'],
            ];
        }

        return $result;
    }

    /**
     * Add music from suggestions to the music plan.
     * Finds the appropriate slot by name or creates one if needed.
     */
    public function addMusicToMusicPlan(int $musicId, string $slotName): void
    {
        if (! $this->musicPlanId) {
            return;
        }

        $musicPlan = \App\Models\MusicPlan::findOrFail($this->musicPlanId);
        $this->authorize('update', $musicPlan);

        // Find or create the slot
        $slot = \App\Models\MusicPlanSlot::firstOrCreate(
            ['name' => $slotName, 'is_custom' => false],
            ['description' => null]
        );

        // Check if slot is already attached to this plan
        $existingSlotPlan = DB::table('music_plan_slot_plan')
            ->where('music_plan_id', $musicPlan->id)
            ->where('music_plan_slot_id', $slot->id)
            ->first();

        $isNewSlot = false;
        $slotPlanId = null;
        if ($existingSlotPlan) {
            $slotPlanId = $existingSlotPlan->id;
        } else {
            // Attach slot to plan with next sequence
            $maxSequence = DB::table('music_plan_slot_plan')
                ->where('music_plan_id', $musicPlan->id)
                ->max('sequence') ?? 0;

            $slotPlanId = DB::table('music_plan_slot_plan')->insertGetId([
                'music_plan_id' => $musicPlan->id,
                'music_plan_slot_id' => $slot->id,
                'sequence' => $maxSequence + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $isNewSlot = true;
        }

        // Determine next music_sequence within this slot instance
        $maxMusicSequence = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $slotPlanId)
            ->max('music_sequence');
        $musicSequence = ($maxMusicSequence ?: 0) + 1;

        // Create the assignment
        \App\Models\MusicPlanSlotAssignment::create([
            'music_plan_slot_plan_id' => $slotPlanId,
            'music_plan_id' => $musicPlan->id,
            'music_plan_slot_id' => $slot->id,
            'music_id' => $musicId,
            'music_sequence' => $musicSequence,
        ]);

        // If a new slot was created, tell the parent to re-render its slot list.
        if ($isNewSlot) {
            $this->dispatch('slot-list-changed');
        }

        // Tell the specific slot-plan child to refresh its assignments.
        $this->dispatch('slot-assignments-refreshed', pivotId: $slotPlanId);

        // Notify the editor for the status message (includes slotPlanId for parent).
        $this->dispatch('music-added-from-suggestions', musicId: $musicId, slotName: $slotName, slotPlanId: $slotPlanId);
    }
};
?>

<div>
    @if ($celebrationsWithScores->isEmpty())
    <flux:callout color="amber" icon="information-circle" class="mt-8">
        <flux:callout.text>Még nincs elég sok énekrend az adatbázisunkban, hogy énekeket tudjunk ajánlani.</flux:callout.text>
    </flux:callout>
    @else
    <!-- Tabs navigation with mary-ui -->
    <x-mary-tabs wire:model="activeTab" class="mb-8">
        <x-mary-tab name="music" icon="o-musical-note" label="Énekjavaslatok ({{ count($slotMusicMap) }})">
            @island(defer: true, always: true)
            @placeholder
            <flux:skeleton.group animate="shimmer" class="flex items-center gap-4">
                <div class="flex flex-col gap-2 w-full">
                    <flux:skeleton.line />
                    <flux:skeleton.line />
                    <flux:skeleton.line />
                    <flux:skeleton.line />
                </div>
            </flux:skeleton.group>
            @endplaceholder

            <!-- Relevance Score Explanation -->
            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                <div class="text-sm text-amber-900 dark:text-amber-100 flex items-center gap-2">
                    <span class="text-yellow-500">★-★★★★</span><span class="text-xs opacity-90">A csillagok száma mutatja, hogy az ünnephez mennyire illeszkedő énekrendből származik a javaslat.</span>
                </div>
            </div>

            <div class="space-y-10" role="tabpanel" id="music-panel" aria-labelledby="music-tab">
                @forelse ($slotMusicMap as $slotName => $slotData)
                @php
                $slot = $slotData['slot'];
                $musics = $slotData['musics'];
                @endphp
                <div class="relative rounded-lg border-2 border-gray-200 dark:border-gray-700 {{ $loop->odd ? 'bg-gray-50 dark:bg-gray-900/50' : 'bg-white dark:bg-gray-800/50' }}" wire:key="slotMusicMap-{{ $slotName }}">
                    <div class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-800 dark:to-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-[calc(0.5rem-2px)]">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <flux:heading size="lg" class="text-gray-900 dark:text-gray-100">{{ $slotName }}</flux:heading>
                                <flux:text class="hidden sm:inline text-gray-600 dark:text-gray-400 text-sm">
                                    ({{ count($musics) }} javaslat)
                                </flux:text>
                                @if($slot->description)
                                <flux:text class="text-gray-500 dark:text-gray-500 text-xs">
                                    {{ $slot->description }}
                                </flux:text>
                                @endif
                            </div>
                            <flux:badge color="blue" size="sm" class="font-semibold">
                                {{ $loop->iteration }}/{{ count($slotMusicMap) }}
                            </flux:badge>
                        </div>
                    </div>
                    <div class="p-6">

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                            @foreach ($musics as $musicItem)
                            @php
                            $music = $musicItem['music'];
                            $score = $musicItem['celebration_score'];
                            $sequence = $musicItem['music_sequence'];
                            $collectionInfo = $musicItem['collection_info'];
                            $celebration = $musicItem['celebration'];
                            @endphp
                            <div class="relative" wire:key="slotMusicMap-{{ $slotName }}-{{ $music->id }}">
                                <livewire:music-card :music="$music" :score="$score" :scope_label="$musicItem['scope_label']" />
                                @if($musicPlanId)
                                <div class="absolute top-2 right-2">
                                    <flux:button
                                        wire:click="addMusicToMusicPlan({{ $music->id }}, '{{ $slotName }}')"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        icon="plus"
                                        variant="primary"
                                        size="sm"
                                        title="Zene hozzáadása az énekrendhez" />
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @empty
                <flux:callout color="zinc" icon="information-circle">
                    <flux:callout.heading>Nincs énekjavaslat</flux:callout.heading>
                    <flux:callout.text>Ehhez a szekcióhoz még nem tartoznak énekjavaslatok.</flux:callout.text>
                </flux:callout>
                @endforelse
            </div>
            @endisland

        </x-mary-tab>

        <x-mary-tab name="plans" icon="lucide.list-music" label="Énekrendek ({{ $musicPlans->count() }})">
            <div class="space-y-5" role="tabpanel" id="plans-panel" aria-labelledby="plans-tab">
                @foreach ($musicPlans as $plan)
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4" wire:key="musicPlanSuggestion-{{ $plan->id }}">
                    <div class="flex-1">
                        <livewire:music-plan-card-extended lazy :musicPlan="$plan" class="max-w-full" :showOpenButton="true" :musicPlanId="$musicPlanId" />
                    </div>
                </div>
                @endforeach
            </div>
        </x-mary-tab>
    </x-mary-tabs>
    @endif
</div>