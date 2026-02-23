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

    <!-- Reusable suggestions content component -->
    <livewire:suggestions-content :criteria="$criteria" />
</div>