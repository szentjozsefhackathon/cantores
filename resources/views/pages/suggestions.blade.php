<?php

namespace App\Livewire\Pages;

use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
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

    /** @var array<string, array<int, array{music: \App\Models\Music, celebration_score: int, music_sequence: int}>> */
    public array $slotMusicMap = [];

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
        $realmId = $user?->current_realm_id;

        // Get music plans that have at least one of the celebrations
        $query = MusicPlan::whereHas('celebrations', function ($q) use ($celebrationIds) {
            $q->whereIn('celebrations.id', $celebrationIds);
        })
            ->with(['celebrations', 'musicAssignments.music', 'musicAssignments.musicPlanSlot'])
            ->withCount('celebrations');

        // Filter by realm: include plans that belong to the current realm OR have no realm
        if ($realmId !== null) {
            $query->where(function ($q) use ($realmId) {
                $q->whereNull('realm_id')
                    ->orWhere('realm_id', $realmId);
            });
        }

        // Exclude the authenticated user's own private plans? We'll include all for now.
        // If you want to exclude user's own private plans, you can add:
        // if ($user) {
        //     $query->where(function ($q) use ($user) {
        //         $q->where('is_published', true)
        //             ->orWhere('user_id', $user->id);
        //     });
        // } else {
        //     $query->where('is_published', true);
        // }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Aggregate music selections by slot, sorted according to requirements.
     *
     * @return array<string, array<int, array{music: \App\Models\Music, celebration_score: int, music_sequence: int}>>
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
                if (!$slot) {
                    continue;
                }

                $slotKey = $slot->id;
                $slotName = $slot->name;
                $priority = $slot->priority;

                // Use a composite key for sorting: priority + slot name
                $sortKey = sprintf('%04d-%s', $priority, $slotName);

                if (!isset($slotMap[$sortKey])) {
                    $slotMap[$sortKey] = [
                        'slot' => $slot,
                        'musics' => [],
                    ];
                }

                // Add music with celebration score and sequence
                $slotMap[$sortKey]['musics'][] = [
                    'music' => $assignment->music,
                    'celebration_score' => $maxScore,
                    'music_sequence' => $assignment->music_sequence ?? 0,
                ];
            }
        }

        // Sort slots by priority then slot name (already encoded in sortKey)
        ksort($slotMap);

        // For each slot, sort musics first by celebration score descending, then by music_sequence ascending
        foreach ($slotMap as &$slotData) {
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

<div class="max-w-3xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
<div class="py-8">
    <flux:heading>
        {{ __('Énekrend javaslatok') }}
    </flux:heading>

    @if ($celebrationsWithScores->isEmpty())
        <flux:callout color="zinc" icon="information-circle" class="mt-4">
            <flux:callout.heading>Nincs találat</flux:callout.heading>
            <flux:callout.text>A megadott kritériumokhoz nem található kapcsolódó ünnep.</flux:callout.text>
        </flux:callout>
    @else
        <div class="mt-6 space-y-8">
            <!-- Celebrations with scores -->
            <div>
                <flux:heading size="lg" class="mb-4">Kapcsolódó ünnepek ({{ $celebrationsWithScores->count() }})</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($celebrationsWithScores as $item)
                        <flux:card class="p-4">
                            <flux:heading size="md">{{ $item['celebration']->name }}</flux:heading>
                            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
                                <div>{{ $item['celebration']->actual_date->translatedFormat('Y. m. d.') }}</div>
                                <div>Érték: <flux:badge color="blue">{{ $item['score'] }}</flux:badge></div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>

            <!-- Music plans -->
            <div>
                <flux:heading size="lg" class="mb-4">Énekrendek ({{ $musicPlans->count() }})</flux:heading>
                <div class="space-y-3">
                    @foreach ($musicPlans as $plan)
                        <flux:card class="p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <flux:heading size="sm">{{ $plan->celebrationName ?? 'Ismeretlen' }}</flux:heading>
                                    <div class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $plan->actual_date?->translatedFormat('Y. m. d.') ?? 'Nincs dátum' }}
                                        • {{ $plan->realm?->name ?? 'Nincs realm' }}
                                        • {{ $plan->is_published ? 'Közzétéve' : 'Privát' }}
                                    </div>
                                </div>
                                <flux:badge color="{{ $plan->is_published ? 'green' : 'zinc' }}">
                                    {{ $plan->is_published ? 'Közzétéve' : 'Privát' }}
                                </flux:badge>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>

            <!-- Aggregated music by slot -->
            <div>
                <flux:heading size="lg" class="mb-4">Énekjavaslatok szekciók szerint</flux:heading>
                <div class="space-y-6">
                    @foreach ($slotMusicMap as $slotName => $musics)
                        <div>
                            <flux:heading size="md" class="mb-2">{{ $slotName }}</flux:heading>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                @foreach ($musics as $musicItem)
                                    @php
                                        $music = $musicItem['music'];
                                        $score = $musicItem['celebration_score'];
                                        $sequence = $musicItem['music_sequence'];
                                    @endphp
                                    <flux:card class="p-3 hover:shadow-md transition-shadow">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <flux:heading size="sm" class="truncate">{{ $music->title }}</flux:heading>
                                                @if ($music->subtitle)
                                                    <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">{{ $music->subtitle }}</flux:text>
                                                @endif
                                                <div class="mt-2 flex items-center gap-2">
                                                    <flux:badge size="xs" color="blue">Érték: {{ $score }}</flux:badge>
                                                    <flux:badge size="xs" color="zinc">Sorszám: {{ $sequence }}</flux:badge>
                                                </div>
                                            </div>
                                            <flux:icon name="musical-note" class="h-5 w-5 text-blue-500 ml-2" variant="mini" />
                                        </div>
                                    </flux:card>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
</div>