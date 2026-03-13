<?php

namespace App\Livewire\Pages;

use App\Facades\GenreContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Services\CelebrationSearchService;
use App\Services\LiturgicalInfoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public function rendering(View $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';

        $view->layout($layout);
    }

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

        // Fetch celebration details from external API based on the requested date
        $this->fetchCelebrationDetails();
    }

    /**
     * Fetch celebration details from the external API using the caching service.
     * Uses the date and name from the request criteria to get the exact celebration
     * as returned by the LiturgicalInfo service — no scoring involved.
     */
    protected function fetchCelebrationDetails(): void
    {
        $date = $this->criteria['date'] ?? null;
        $name = $this->criteria['name'] ?? null;

        if (! $date) {
            return;
        }

        $service = app(LiturgicalInfoService::class);
        $celebrations = $service->getCelebrations($date);

        if (empty($celebrations)) {
            return;
        }

        // If we have a name, find the exact match; otherwise use the first celebration
        if ($name) {
            foreach ($celebrations as $celebration) {
                if (($celebration['name'] ?? $celebration['title'] ?? null) === $name) {
                    $this->celebrationDetails = $celebration;
                    return;
                }
            }
        }

        $this->celebrationDetails = $celebrations[0];
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
            'date' => $celebration->actual_date?->format('Y-m-d'),
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
        $this->redirectRoute('suggestions', $criteria, navigate: true);
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
                'musicAssignments.music' => fn ($q) => $q->visibleTo($user),
                'musicAssignments.music.collections' => fn ($q) => $q->visibleTo($user),
                'musicAssignments.musicPlanSlot' => fn ($q) => $q->visibleToUser($user),
            ]);

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
        <div class="pt-1 dark:border-neutral-800 hidden md:block">
            <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 text-white/80">
                Adatforrás: <a href="https://szentjozsefhackathon.github.io/napi-lelki-batyu/" class="hover:underline">Szt. József Hackathon Napi Lelki Batyu</a>
            </flux:text>
        </div>

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
                            @if (isset($part[0]) && is_array($part[0]))
                                @foreach ($part as $subIndex => $subPart)
                                    @php $subLabel = ($subPart['short_title'] ?? 'Rész') . (isset($subPart['cause']) ? ' (' . $subPart['cause'] . ')' : ''); @endphp
                                    <x-mary-tab name="part-{{ $partIndex }}-{{ $subIndex }}" label="{{ $subLabel }}">
                                        <div class="rounded-2xl border border-zinc-200 bg-amber-50/80 dark:bg-zinc-900 p-4 dark:border-zinc-800 p-2 font-serif shadow-lg">
                                            <div class="grid grid-cols-1 gap-2 text-sm ">
                                                <div>
                                                    <flux:heading class="inline">{{ $subPart['ref'] ?? '' }}</flux:heading>
                                                    <flux:text class="inline">{!! $this->sanitize($subPart['teaser'] ?? '') !!}</flux:text>
                                                </div>
                                                <div>{{ $subPart['title'] ?? '' }}</div>
                                                <div
                                                    x-data="{ expanded: false }"
                                                    class="text-sm"
                                                >
                                                    @php
                                                        $fullText = $this->sanitize($subPart['text'] ?? '');
                                                        $truncated = mb_strlen($fullText) > 100 ? mb_substr($fullText, 0, 100) . '...' : $fullText;
                                                        $needsExpand = mb_strlen($fullText) > 100;
                                                    @endphp
                                                    <span x-show="!expanded">
                                                        {!! $truncated !!}
                                                    </span>
                                                    <span x-show="expanded" style="display: none;">
                                                        {!! $fullText !!}
                                                    </span>
                                                    @if($needsExpand)
                                                        <button
                                                            @click="expanded = !expanded"
                                                            class="ml-2 text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium"
                                                        >
                                                            <span x-show="!expanded">{{ __('Bővebben') }}</span>
                                                            <span x-show="expanded" style="display: none;">{{ __('Összecsukás') }}</span>
                                                        </button>
                                                    @endif
                                                </div>
                                                <div>{{ $subPart['ending'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </x-mary-tab>
                                @endforeach
                            @else
                                <x-mary-tab name="part-{{ $partIndex }}" label="{{ $part['short_title'] ?? 'Rész ' . ($partIndex + 1) }}">
                                    <div class="rounded-2xl border border-zinc-200 bg-amber-50/80 dark:bg-zinc-900 p-4 dark:border-zinc-800 p-2 font-serif shadow-lg">
                                        <div class="grid grid-cols-1 gap-2 text-sm ">
                                            <div>
                                                <flux:heading class="inline">{{ $part['ref'] ?? '' }}</flux:heading>
                                                <flux:text class="inline">{!! $this->sanitize($part['teaser'] ?? '') !!}</flux:text>
                                            </div>
                                            <div>{{ $part['title'] ?? '' }}</div>
                                            <div
                                                x-data="{ expanded: false }"
                                                class="text-sm"
                                            >
                                                @php
                                                    $fullText = $this->sanitize($part['text'] ?? '');
                                                    $truncated = mb_strlen($fullText) > 100 ? mb_substr($fullText, 0, 100) . '...' : $fullText;
                                                    $needsExpand = mb_strlen($fullText) > 100;
                                                @endphp
                                                <span x-show="!expanded">
                                                    {!! $truncated !!}
                                                </span>
                                                <span x-show="expanded" style="display: none;">
                                                    {!! $fullText !!}
                                                </span>
                                                @if($needsExpand)
                                                    <button
                                                        @click="expanded = !expanded"
                                                        class="ml-2 text-blue-600 dark:text-blue-400 hover:underline text-xs font-medium"
                                                    >
                                                        <span x-show="!expanded">{{ __('Bővebben') }}</span>
                                                        <span x-show="expanded" style="display: none;">{{ __('Összecsukás') }}</span>
                                                    </button>
                                                @endif
                                            </div>
                                            <div>{{ $part['ending'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </x-mary-tab>
                            @endif
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