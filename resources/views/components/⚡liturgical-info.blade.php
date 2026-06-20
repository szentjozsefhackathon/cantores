<?php

use App\Facades\GenreContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Services\CelebrationSearchService;
use App\Services\LiturgicalInfoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public array $celebrations = [];

    public string $date;

    public bool $loading = true;

    public ?string $error = null;

    public bool $selectable = false;

    public bool $welcome = false;

    /**
     * Memoized map of exact-match Celebration models keyed by "name|date", built once per render.
     *
     * @var \Illuminate\Support\Collection<string, Celebration>|null
     */
    private ?\Illuminate\Support\Collection $exactCelebrations = null;

    /**
     * Memoized music plans for the displayed celebrations, grouped by celebration ID.
     *
     * @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, MusicPlan>>|null
     */
    private ?\Illuminate\Support\Collection $plansByCelebrationId = null;

    /**
     * Memoized suggestion-existence flags keyed by celebration loop index.
     *
     * @var array<int, bool>|null
     */
    private ?array $suggestionFlags = null;

    /**
     * Memoized related-celebration IDs, both per loop index and as a de-duplicated union.
     *
     * @var array{perIndex: array<int, array<int, int>>, all: array<int, int>}|null
     */
    private ?array $relatedIds = null;

    /**
     * Memoized song-preview lists keyed by celebration loop index. Each item carries the
     * slot name, song title and incipit URL for a suggested song that has a visible incipit.
     *
     * @var array<int, array<int, array{music_id: int, title: string, slot: string, incipit_url: string}>>|null
     */
    private ?array $suggestionPreviews = null;

    public function mount(bool $selectable = false, bool $welcome = false): void
    {
        $this->welcome = $welcome;
        $selectable = $selectable;
        $this->date = Carbon::now()->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    /**
     * Placeholder rendered while the component is lazy-loaded, so the dashboard
     * request returns immediately instead of blocking on the remote fetch.
     */
    public function placeholder(): string
    {
        return <<<'HTML'
        <div>
            <flux:card class="liturgical-info p-0 overflow-hidden border-0 shadow-xl dark:shadow-neutral-900/30">
                <div class="text-center py-16 space-y-4">
                    <flux:icon.loading class="h-12 w-12 mx-auto text-blue-600" />
                    <flux:heading size="md">Liturgikus információk betöltése…</flux:heading>
                </div>
            </flux:card>
        </div>
        HTML;
    }

    public function fetchLiturgicalInfo(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $service = app(LiturgicalInfoService::class);
            $celebrations = $service->getCelebrations($this->date);

            if ($celebrations !== null) {
                $this->celebrations = $celebrations;
            } else {
                $this->error = 'Failed to fetch liturgical information.';
            }
        } catch (\Exception) {
            $this->error = 'An error occurred while fetching data.';
        } finally {
            $this->loading = false;
        }
    }

    public function refresh(): void
    {
        $this->fetchLiturgicalInfo();
    }

    public function updatedDate(): void
    {
        $this->fetchLiturgicalInfo();
    }

    public function today(): void
    {
        $this->date = Carbon::now()->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    public function importantLinks(): array
    {
        return [
            [
                'key' => 'igenaptar',
                'label' => 'Igenaptár',
                'href' => sprintf('https://igenaptar.katolikus.hu/nap/index.php?holnap=%s', $this->date),
                'icon' => 'calendar-days',
            ],
        ];
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    public function previousSunday(): void
    {
        $current = Carbon::parse($this->date);
        $daysSinceSunday = $current->dayOfWeek === Carbon::SUNDAY ? 7 : $current->dayOfWeek;

        $this->date = $current->subDays($daysSinceSunday)->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    public function nextSunday(): void
    {
        $current = Carbon::parse($this->date);
        // If today is Sunday, go to the next Sunday (7 days ahead), otherwise move to next Sunday
        $daysUntilSunday = (7 - $current->dayOfWeek) % 7;
        if ($daysUntilSunday === 0) {
            $daysUntilSunday = 7;
        }
        $this->date = $current->addDays($daysUntilSunday)->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        // No action needed, just trigger re-render to refresh existing plans list
    }

    public function createMusicPlan(int $celebrationIndex): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (! isset($this->celebrations[$celebrationIndex])) {
            return;
        }

        $celebrationData = $this->celebrations[$celebrationIndex];

        // Update or create Celebration (ensure data matches liturgical info)
        $celebration = Celebration::updateOrCreate(
            [
                'actual_date' => $celebrationData['dateISO'] ?? $this->date,
                'celebration_key' => $celebrationData['celebrationKey'] ?? 0,
                'is_custom' => false, // Ensure these celebrations are marked as non-custom
            ],
            [
                'name' => $celebrationData['name'] ?? $celebrationData['title'] ?? 'Unknown',
                'season' => (int) ($celebrationData['season'] ?? 0),
                'season_text' => $celebrationData['seasonText'] ?? null,
                'color_id' => $celebrationData['colorId'] ?? null,
                'color_text' => $celebrationData['colorText'] ?? null,
                'week' => (int) ($celebrationData['week'] ?? 0),
                'day' => (int) ($celebrationData['dayofWeek'] ?? 0),
                'readings_code' => $celebrationData['readingsId'] ?? null,
                'year_letter' => $celebrationData['yearLetter'] ?? null,
                'year_parity' => $celebrationData['yearParity'] ?? null,
            ]
        );

        // Create MusicPlan without celebration fields
        $musicPlan = MusicPlan::create([
            'user_id' => $user->id,
            'genre_id' => GenreContext::getId(),
            'is_private' => true,
        ]);

        // Associate celebration with the new music plan
        $musicPlan->celebration()->associate($celebration);
        $musicPlan->save();

        // Redirect to MusicPlanEditor page with the created plan
        $this->redirectRoute('music-plan-editor', ['musicPlan' => $musicPlan->id], navigate: true);
    }

    public function selectCelebration(int $celebrationIndex): void
    {
        if (! isset($this->celebrations[$celebrationIndex])) {
            return;
        }

        $celebrationData = $this->celebrations[$celebrationIndex];

        // Update or create Celebration (ensure data matches liturgical info)
        $celebration = Celebration::updateOrCreate(
            [
                'actual_date' => $celebrationData['dateISO'] ?? $this->date,
                'celebration_key' => $celebrationData['celebrationKey'] ?? 0,
                'is_custom' => false, // Ensure these celebrations are marked as non-custom
            ],
            [
                'name' => $celebrationData['name'] ?? $celebrationData['title'] ?? 'Unknown',
                'season' => (int) ($celebrationData['season'] ?? 0),
                'season_text' => $celebrationData['seasonText'] ?? null,
                'color_id' => $celebrationData['colorId'] ?? null,
                'color_text' => $celebrationData['colorText'] ?? null,
                'week' => (int) ($celebrationData['week'] ?? 0),
                'day' => (int) ($celebrationData['dayofWeek'] ?? 0),
                'readings_code' => $celebrationData['readingsId'] ?? null,
                'year_letter' => $celebrationData['yearLetter'] ?? null,
                'year_parity' => $celebrationData['yearParity'] ?? null,
            ]
        );

        // Emit event with celebration ID for parent component to handle
        $this->dispatch('celebration-selected', celebrationId: $celebration->id);
    }

    /**
     * Build the "name|date" lookup key for a celebration payload.
     */
    private function celebrationKey(array $celebrationData): ?string
    {
        $name = $celebrationData['name'] ?? $celebrationData['title'] ?? null;
        if (! $name) {
            return null;
        }

        return $name . '|' . ($celebrationData['dateISO'] ?? $this->date);
    }

    /**
     * Load every exact-match Celebration for the displayed celebrations in a single query,
     * keyed by "name|date". Memoized so it runs once per render.
     *
     * @return \Illuminate\Support\Collection<string, Celebration>
     */
    private function exactCelebrations(): \Illuminate\Support\Collection
    {
        if ($this->exactCelebrations !== null) {
            return $this->exactCelebrations;
        }

        $pairs = collect($this->celebrations)
            ->map(fn(array $celebrationData): array => [
                'name' => $celebrationData['name'] ?? $celebrationData['title'] ?? null,
                'date' => $celebrationData['dateISO'] ?? $this->date,
            ])
            ->filter(fn(array $pair): bool => $pair['name'] !== null);

        if ($pairs->isEmpty()) {
            return $this->exactCelebrations = collect();
        }

        $query = Celebration::query();
        foreach ($pairs as $pair) {
            $query->orWhere(function ($q) use ($pair) {
                $q->where('name', $pair['name'])->where('actual_date', $pair['date']);
            });
        }

        return $this->exactCelebrations = $query->get()
            ->keyBy(fn(Celebration $celebration): string => $celebration->name . '|' . $celebration->actual_date->format('Y-m-d'));
    }

    /**
     * Load all music plans for the displayed celebrations in one query, grouped by celebration ID.
     * Memoized so it runs once per render.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, MusicPlan>>
     */
    private function plansByCelebrationId(): \Illuminate\Support\Collection
    {
        if ($this->plansByCelebrationId !== null) {
            return $this->plansByCelebrationId;
        }

        $celebrationIds = $this->exactCelebrations()->pluck('id')->all();

        if ($celebrationIds === []) {
            return $this->plansByCelebrationId = collect();
        }

        $user = Auth::user();

        return $this->plansByCelebrationId = MusicPlan::whereIn('celebration_id', $celebrationIds)
            ->with([
                'user',
                'genre',
                'celebration',
                'musicAssignments.music' => fn($q) => $q->visibleTo($user),
                'musicAssignments.music.genres',
                'musicAssignments.music.collections',
                'musicAssignments.music.publicPreviewScores',
                'musicAssignments.music.scores',
                'musicAssignments.musicPlanSlotPlan.musicPlanSlot' => fn($q) => $q->visibleToUser($user),
            ])
            ->withCount(['slots', 'musicAssignments'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('celebration_id');
    }

    /**
     * Get the music plans attached to a celebration from the memoized, pre-grouped collection.
     *
     * @return \Illuminate\Support\Collection<int, MusicPlan>
     */
    private function plansForCelebration(array $celebrationData): \Illuminate\Support\Collection
    {
        $key = $this->celebrationKey($celebrationData);
        $celebration = $key !== null ? $this->exactCelebrations()->get($key) : null;

        if (! $celebration) {
            return collect();
        }

        return $this->plansByCelebrationId()->get($celebration->id, collect());
    }

    /**
     * @return \Illuminate\Support\Collection<int, MusicPlan>
     */
    public function getExistingMusicPlans(array $celebrationData): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        $genreId = GenreContext::getId();

        return $this->plansForCelebration($celebrationData)
            ->filter(function (MusicPlan $plan) use ($user, $genreId): bool {
                if ((int) $plan->user_id !== (int) $user->id) {
                    return false;
                }

                // Show plans that belong to the current genre OR have no genre (belongs to all).
                return $genreId === null || $plan->genre_id === null || (int) $plan->genre_id === $genreId;
            })
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MusicPlan>
     */
    public function getPublishedMusicPlans(array $celebrationData): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        $genreId = GenreContext::getId();

        return $this->plansForCelebration($celebrationData)
            ->filter(function (MusicPlan $plan) use ($user, $genreId): bool {
                if ($plan->is_private) {
                    return false;
                }

                // Exclude the authenticated user's own plans (if logged in).
                if ($user && (int) $plan->user_id === (int) $user->id) {
                    return false;
                }

                // Show plans that belong to the current genre OR have no genre (belongs to all).
                return $genreId === null || $plan->genre_id === null || (int) $plan->genre_id === $genreId;
            })
            ->values();
    }

    /**
     * Build the ordered, de-duplicated song-preview list for a single music plan's carousel. Each
     * item carries its incipit URL when one is visible so the teaser can show a notated snippet
     * where available and fall back to the title otherwise, mirroring the suggestion carousel.
     *
     * @return array<int, array{music: \App\Models\Music, title: string, slot: string, slot_priority: int, incipit_url: ?string}>
     */
    public function planPreviews(MusicPlan $plan): array
    {
        $user = Auth::user();
        $seen = [];
        $items = [];

        foreach ($plan->musicAssignments as $assignment) {
            $music = $assignment->music;
            if (! $music) {
                continue;
            }

            $slot = $assignment->musicPlanSlotPlan?->musicPlanSlot;
            $seenKey = $music->id.'|'.($slot?->id ?? '');

            if (isset($seen[$seenKey])) {
                continue;
            }

            $incipit = $music->visibleIncipitScores($user)->first();

            $seen[$seenKey] = true;
            $items[] = [
                'music' => $music,
                'title' => $music->title,
                'slot' => $slot?->name ?? __('Ének'),
                'slot_priority' => $slot?->priority ?? PHP_INT_MAX,
                'incipit_url' => $incipit === null
                    ? null
                    : ($incipit->public_preview ? $incipit->publicIncipitUrl() : $incipit->incipitUrl()),
            ];
        }

        usort($items, fn(array $a, array $b): int => [$a['slot_priority'], $a['slot']] <=> [$b['slot_priority'], $b['slot']]);

        return $items;
    }

    /**
     * Build the CelebrationSearchService criteria for a celebration payload.
     *
     * @return array<string, mixed>
     */
    private function criteriaFor(array $celebrationData): array
    {
        return array_filter([
            'name' => $celebrationData['name'] ?? $celebrationData['title'] ?? null,
            'season' => isset($celebrationData['season']) ? (int) $celebrationData['season'] : null,
            'week' => isset($celebrationData['week']) ? (int) $celebrationData['week'] : null,
            'day' => isset($celebrationData['dayofWeek']) ? (int) $celebrationData['dayofWeek'] : null,
            'readings_code' => $celebrationData['readingsId'] ?? null,
            'year_letter' => $celebrationData['yearLetter'] ?? null,
            'year_parity' => $celebrationData['yearParity'] ?? null,
        ], fn($value): bool => $value !== null);
    }

    /**
     * Resolve related-celebration IDs for every displayed celebration through a single shared
     * service instance, so the full-table scan it performs runs only once per render. Returns the
     * IDs both per loop index (scored order preserved) and as a de-duplicated union.
     *
     * @return array{perIndex: array<int, array<int, int>>, all: array<int, int>}
     */
    private function relatedIds(): array
    {
        if ($this->relatedIds !== null) {
            return $this->relatedIds;
        }

        $service = app(CelebrationSearchService::class);

        $perIndex = [];
        $all = [];

        foreach ($this->celebrations as $index => $celebrationData) {
            $ids = $service->findRelated($this->criteriaFor($celebrationData))->pluck('id')->all();
            $perIndex[$index] = $ids;
            $all = array_merge($all, $ids);
        }

        return $this->relatedIds = [
            'perIndex' => $perIndex,
            'all' => array_values(array_unique($all)),
        ];
    }

    /**
     * Get the suggested-song previews for a celebration (by loop index).
     *
     * @return array<int, array{music: \App\Models\Music, title: string, slot: string, slot_priority: int, incipit_url: ?string}>
     */
    public function suggestionPreviewsFor(int $celebrationIndex): array
    {
        return $this->suggestionPreviews()[$celebrationIndex] ?? [];
    }

    /**
     * Build, for every displayed celebration, an ordered and de-duplicated list of suggested songs
     * for the engagement carousel. Each item carries its incipit URL when one is visible, so the
     * teaser can show a notated snippet where available and fall back to the title otherwise.
     *
     * Music plans for the unioned related celebrations are loaded in a single query (with their
     * assignments, slots and incipit scores eager-loaded), then walked per index in celebration
     * score order. Memoized so the work runs once per render rather than per card.
     *
     * @return array<int, array<int, array{music: \App\Models\Music, title: string, slot: string, slot_priority: int, incipit_url: ?string}>>
     */
    private function suggestionPreviews(): array
    {
        if ($this->suggestionPreviews !== null) {
            return $this->suggestionPreviews;
        }

        $related = $this->relatedIds();

        if ($related['all'] === []) {
            return $this->suggestionPreviews = [];
        }

        $user = Auth::user();
        $genreId = GenreContext::getId();

        $query = MusicPlan::whereIn('celebration_id', $related['all'])
            ->with([
                'musicAssignments.music' => fn($q) => $q->visibleTo($user),
                'musicAssignments.music.genres',
                'musicAssignments.music.collections',
                'musicAssignments.music.publicPreviewScores',
                'musicAssignments.music.scores',
                'musicAssignments.musicPlanSlotPlan.musicPlanSlot' => fn($q) => $q->visibleToUser($user),
            ]);

        if ($genreId !== null) {
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')->orWhere('genre_id', $genreId);
            });
        }

        $plansByCelebration = $query->visibleTo($user)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('celebration_id');

        $previews = [];

        foreach ($related['perIndex'] as $index => $celebrationIds) {
            $seen = [];
            $items = [];

            foreach ($celebrationIds as $celebrationId) {
                foreach ($plansByCelebration->get($celebrationId, collect()) as $plan) {
                    foreach ($plan->musicAssignments as $assignment) {
                        $music = $assignment->music;
                        if (! $music) {
                            continue;
                        }

                        if ($genreId !== null) {
                            $musicGenreIds = $music->genres->pluck('id')->all();
                            if ($musicGenreIds !== [] && ! in_array($genreId, $musicGenreIds, true)) {
                                continue;
                            }
                        }

                        $slot = $assignment->musicPlanSlotPlan?->musicPlanSlot;
                        $seenKey = $music->id.'|'.($slot?->id ?? '');

                        if (isset($seen[$seenKey])) {
                            continue;
                        }

                        $incipit = $music->visibleIncipitScores($user)->first();

                        $seen[$seenKey] = true;
                        $items[] = [
                            'music' => $music,
                            'title' => $music->title,
                            'slot' => $slot?->name ?? __('Ének'),
                            'slot_priority' => $slot?->priority ?? PHP_INT_MAX,
                            'incipit_url' => $incipit === null
                                ? null
                                : ($incipit->public_preview ? $incipit->publicIncipitUrl() : $incipit->incipitUrl()),
                        ];
                    }
                }
            }

            // Order songs by their slot position (priority then name), mirroring the suggestions list.
            usort($items, fn(array $a, array $b): int => [$a['slot_priority'], $a['slot']] <=> [$b['slot_priority'], $b['slot']]);

            $previews[$index] = $items;
        }

        return $this->suggestionPreviews = $previews;
    }

    /**
     * Check whether a celebration (by loop index) has any music plan suggestions.
     * Returns true when at least one related celebration has a published plan attached
     * or one of the authenticated user's own plans attached.
     */
    public function hasSuggestions(int $celebrationIndex): bool
    {
        return $this->suggestionFlags()[$celebrationIndex] ?? false;
    }

    /**
     * Compute suggestion-existence flags for every displayed celebration at once.
     *
     * Related celebrations are scored against the (memoized) full celebration list, then a single
     * query resolves which of the unioned related IDs actually carry a matching plan. Memoized so
     * the full-table scan and the existence query each run once per render rather than per card.
     *
     * @return array<int, bool>
     */
    private function suggestionFlags(): array
    {
        if ($this->suggestionFlags !== null) {
            return $this->suggestionFlags;
        }

        $related = $this->relatedIds();
        $relatedIdsPerIndex = $related['perIndex'];
        $allRelatedIds = $related['all'];

        $celebrationIdsWithPlans = [];
        if ($allRelatedIds !== []) {
            $user = Auth::user();
            $genreId = GenreContext::getId();

            $query = MusicPlan::whereIn('celebration_id', $allRelatedIds);

            // Filter by genre: include plans that belong to the current genre OR have no genre.
            if ($genreId !== null) {
                $query->where(function ($q) use ($genreId) {
                    $q->whereNull('genre_id')
                        ->orWhere('genre_id', $genreId);
                });
            }

            // Include published plans OR the user's own plans (if logged in).
            if ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('is_private', false)
                        ->orWhere('user_id', $user->id);
                });
            } else {
                $query->where('is_private', false);
            }

            $celebrationIdsWithPlans = $query->distinct()->pluck('celebration_id')->all();
        }

        $withPlans = array_flip($celebrationIdsWithPlans);

        $flags = [];
        foreach ($relatedIdsPerIndex as $index => $ids) {
            $flags[$index] = array_intersect_key($withPlans, array_flip($ids)) !== [];
        }

        return $this->suggestionFlags = $flags;
    }

    /**
     * Open the suggestions page for the given celebration.
     */
    public function openSuggestions(int $celebrationIndex): void
    {
        if (! isset($this->celebrations[$celebrationIndex])) {
            return;
        }

        $celebrationData = $this->celebrations[$celebrationIndex];

        // Build criteria to pass to suggestions page
        $criteria = [
            'date' => $celebrationData['dateISO'] ?? null,
            'name' => $celebrationData['name'] ?? $celebrationData['title'] ?? null,
            'season' => isset($celebrationData['season']) ? (int) $celebrationData['season'] : null,
            'week' => isset($celebrationData['week']) ? (int) $celebrationData['week'] : null,
            'day' => isset($celebrationData['dayofWeek']) ? (int) $celebrationData['dayofWeek'] : null,
            'readings_code' => $celebrationData['readingsId'] ?? null,
            'year_letter' => $celebrationData['yearLetter'] ?? null,
            'year_parity' => $celebrationData['yearParity'] ?? null,
        ];

        // Remove null values
        $criteria = array_filter($criteria, fn($value) => $value !== null);

        // Store criteria in session or pass as query parameters
        // For now, we'll redirect to suggestions page with query parameters
        $this->redirectRoute('suggestions', $criteria, navigate: true);
    }
};
?>

<div>
    <flux:card class="liturgical-info p-0 overflow-hidden border-0 shadow-xl dark:shadow-neutral-900/30">
        <!-- Header with gradient -->
        <div class="bg-gradient-to-r from-gray-100 to-gray-200 dark:from-indigo-900 dark:to-fuchsia-950 p-6 text-gray-800 dark:text-white">
            <div class="flex flex-col md:flex-row justify-between gap-4">
                <div class="flex items-center gap-4">
                    <flux:icon name="book-open-text" class="h-10 w-10" variant="outline" />
                    <div>
                        @if($welcome)
                        <flux:heading size="xl" class="text-gray-800 dark:text-white">Liturgikus énekrendek</flux:heading>
                        <flux:text class="text-gray-500 dark:text-blue-100">Nézd meg, mások mit énekelnek — vagy állítsd össze és oszd meg a saját énekrendedet!</flux:text>
                        <div class="flex items-center">
                            <flux:heading class="mr-2 text-gray-800 dark:text-white">Műfaj:</flux:heading>
                            <livewire:genre-selector />
                        </div>
                        @else
                        <flux:heading size="xl" class="text-gray-800 dark:text-white">Liturgikus naptár és énekrendek</flux:heading>
                        @endif

                    </div>

                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex items-end gap-2">
                        <flux:button
                            wire:click="today"
                            variant="outline"
                            icon="calendar"
                            icon:variant="mini">
                            Ma
                        </flux:button>
                        <flux:field class="mb-0">
                            <flux:input
                                type="date"
                                wire:model.live="date"
                                variant="outline"
                                class="bg-white/80 border-gray-300 text-gray-800 placeholder-gray-400 dark:bg-white/20 dark:border-white/30 dark:text-white dark:placeholder-white/70"
                                max="{{ Carbon::now()->addYears(1)->format('Y-m-d') }}"
                                min="{{ Carbon::now()->subYears(10)->format('Y-m-d') }}" />
                        </flux:field>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <div class="flex flex-wrap gap-2">
                                <flux:button
                                    square
                                    wire:click="previousDay"
                                    variant="outline"
                                    size="sm"
                                    icon="arrow-left"
                                    icon:variant="mini"
                                    title="Előző nap"
                                    aria-label="Előző nap" />
                                <div class="flex items-center justify-center self-center">
                                    <flux:text size="sm" class="w-38 shrink-0 text-gray-500 dark:text-white/70 text-center">Előző/következő nap</flux:text>
                                </div>
                                <flux:button
                                    square
                                    wire:click="nextDay"
                                    variant="outline"
                                    size="sm"
                                    icon="arrow-right"
                                    icon:variant="mini"
                                    title="Következő nap"
                                    aria-label="Következő nap" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button
                                square
                                wire:click="previousSunday"
                                variant="outline"
                                size="sm"
                                icon="backward"
                                icon:variant="mini"
                                title="Előző vasárnap"
                                aria-label="Előző vasárnap" />
                            <flux:text size="sm" class="w-38 shrink-0 text-gray-500 dark:text-white/70 self-center text-center">Előző/következő vasárnap</flux:text>

                            <flux:button
                                square
                                wire:click="nextSunday"
                                variant="outline"
                                size="sm"
                                icon="forward"
                                icon:variant="mini"
                                title="Következő vasárnap"
                                aria-label="Következő vasárnap" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Selected date display -->
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon name="calendar-days" class="h-5 w-5 text-blue-600 dark:text-blue-400" variant="mini" />
                    <flux:text size="lg">
                        {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('Y. F j., l') }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:button
                        size="sm"
                        variant="filled"
                        x-on:click="$dispatch('open-direktorium', { date: '{{ $date }}' }); $flux.modal('direktorium').show()"
                        class="inline-flex items-center gap-1"
                        icon="book-open-text" icon:variant="mini">

                        Direktórium
                    </flux:button>
                    @foreach ($this->importantLinks() as $link)
                    <flux:link
                        wire:key="important-link-{{ $link['key'] }}"
                        href="{{ $link['href'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white px-3 py-1 text-sm text-neutral-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:border-blue-500 dark:hover:text-blue-300">
                        <flux:icon name="{{ $link['icon'] }}" class="h-3.5 w-3.5" variant="mini" />
                        {{ $link['label'] }}
                        <flux:icon name="external-link" class="h-3.5 w-3.5" variant="mini" />
                    </flux:link>
                    @endforeach
                </div>
            </div>

            @if ($loading)
            <div class="text-center py-12 space-y-4">
                <flux:icon.loading class="h-12 w-12 mx-auto text-blue-600" />
                <flux:heading size="md">Loading liturgical information...</flux:heading>
                <flux:text class="text-neutral-600 dark:text-neutral-400">Fetching data from the liturgical calendar</flux:text>
            </div>
            @elseif ($error)
            <flux:callout color="red" icon="exclamation-circle" class="border-red-200 dark:border-red-800">
                <flux:callout.heading>Unable to Load Data</flux:callout.heading>
                <flux:callout.text>{{ $error }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button wire:click="refresh" variant="ghost" size="sm">Try Again</flux:button>
                </x-slot>
            </flux:callout>
            @elseif (empty($celebrations))
            <flux:callout color="zinc" class="border-zinc-200 dark:border-zinc-800">
                <flux:callout.heading>No Celebrations Found</flux:callout.heading>
                <flux:callout.text>There are no liturgical celebrations recorded for the selected date.</flux:callout.text>
                <x-slot name="actions">
                    <flux:button wire:click="refresh" variant="ghost" size="sm">Check Another Date</flux:button>
                </x-slot>
            </flux:callout>
            @else
            @if ($selectable)
            <div class="grid grid-cols-1 gap-6">
                @else
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    @endif
                    @foreach ($celebrations as $celebration)
                    @php
                    $colorTextColor = \App\Models\Celebration::borderColorClassForColorText($celebration['colorText'] ?? null);
                    @endphp
                    <flux:card class="celebration-card p-0 overflow-hidden border-l-4 {{ $colorTextColor }} hover:shadow-lg transition-shadow duration-300">
                        <div class="p-3 space-y-4">
                            <!-- Title with badges -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <flux:heading size="md" class="inline leading-snug">{{ $celebration['name'] ?? 'No title' }}</flux:heading>
                                    @if (isset($celebration['yearLetter']))
                                    <flux:badge color="blue" size="sm" class="ml-1.5 align-middle">{{ $celebration['yearLetter'] }}</flux:badge>
                                    @endif
                                    @if (isset($celebration['yearParity']))
                                    <flux:badge color="zinc" size="sm" class="ml-1 align-middle">{{ $celebration['yearParity'] }}</flux:badge>
                                    @endif
                                </div>
                                @if (isset($celebration['celebrationType']))
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <flux:icon name="tag" class="h-4 w-4 text-amber-600 dark:text-amber-400" variant="mini" />
                                    <flux:text class="text-sm font-medium">{{ $celebration['celebrationType'] }}</flux:text>
                                </div>
                                @endif
                            </div>

                            <!-- Season info -->
                            @if (isset($celebration['seasonText']))
                            <div class="flex items-center gap-2">
                                <flux:icon name="clock" class="h-4 w-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" variant="mini" />
                                <flux:text class="text-sm font-medium">{{ $celebration['seasonText'] }}</flux:text>
                            </div>
                            @endif

                            @auth
                            @php
                            $existingPlans = $this->getExistingMusicPlans($celebration);
                            @endphp
                            <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                                <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">
                                    Saját énekrendjeid:
                                </flux:heading>
                                @if($existingPlans->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($existingPlans as $plan)
                                    <a
                                        href="{{ route('music-plan-editor', ['musicPlan' => $plan->id]) }}"
                                        class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors group">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <flux:icon name="{{ $plan->genre?->icon() ?? 'musical-note' }}" class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" variant="mini" />
                                            <div class="min-w-0">
                                                <x-user-badge :user="$plan->user" />
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                                        {{ $plan->slots_count }} rész · {{ $plan->music_assignments_count }} zenemű
                                                    </flux:text>
                                                    @if(!$plan->is_private)
                                                    <flux:icon name="globe" class="h-3 w-3 text-neutral-400 inline" variant="mini" />
                                                    @else
                                                    <flux:icon name="globe-lock" class="h-3 w-3 text-neutral-400 inline" variant="mini" />
                                                    @endif
                                                </div>
                                                @if($plan->private_notes)
                                                <flux:text class="text-xs text-neutral-400 dark:text-neutral-500 italic truncate max-w-48 mt-0.5">
                                                    {{ \Illuminate\Support\Str::limit($plan->private_notes, 50) }}
                                                </flux:text>
                                                @endif
                                            </div>
                                        </div>
                                        <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600 shrink-0" variant="mini" />
                                    </a>
                                    @endforeach
                                </div>
                                @else
                                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400 italic">
                                    Még nincs énekrend ehhez az ünnephez{{ GenreContext::getId() ? ' ' . GenreContext::label() . ' műfajban' : '' }}.
                                </flux:text>
                                @endif
                            </div>
                            @endauth

                            @php
                            $publishedPlans = $this->getPublishedMusicPlans($celebration);
                            @endphp
                            <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                                @if($selectable)
                                <flux:button
                                    wire:click="selectCelebration({{ $loop->index }})"
                                    variant="primary"
                                    size="sm"
                                    icon="check-circle"
                                    class="w-full">
                                    Ünnep kiválasztása
                                </flux:button>
                                @endif
                                @if (!$selectable)
                                @php
                                $celebrationIndex = $loop->index;
                                $previews = $this->suggestionPreviewsFor($celebrationIndex);
                                $hasSuggestions = $this->hasSuggestions($celebrationIndex);
                                @endphp
                                @if(!empty($previews))
                                <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">
                                    Énekjavaslatok az ünnepre
                                </flux:heading>
                                <div x-data="{
                                        current: 0,
                                        total: {{ count($previews) }},
                                        timer: null,
                                        go(step) { this.current = (this.current + step + this.total) % this.total; this.start(); },
                                        start() { this.stop(); if (this.total > 1) { this.timer = setInterval(() => { this.current = (this.current + 1) % this.total; }, 4000); } },
                                        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
                                     }"
                                     x-init="start()"
                                     x-on:mouseenter="stop()"
                                     x-on:mouseleave="start()"
                                     wire:key="suggestion-carousel-{{ $celebrationIndex }}-{{ $date }}-{{ \App\Facades\GenreContext::getId() ?? 'all' }}"
                                     class="flex items-stretch gap-1.5">
                                    @if(count($previews) > 1)
                                    <button type="button"
                                        x-on:click.stop="go(-1)"
                                        class="flex shrink-0 items-center justify-center rounded-md px-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-blue-600 dark:text-neutral-500 dark:hover:bg-neutral-800"
                                        aria-label="Előző javaslat">
                                        <flux:icon name="chevron-left" class="h-5 w-5" />
                                    </button>
                                    @endif
                                    <div
                                        wire:click="openSuggestions({{ $celebrationIndex }})"
                                        role="button"
                                        tabindex="0"
                                        x-on:keydown.enter="$wire.openSuggestions({{ $celebrationIndex }})"
                                        class="group/sugg relative h-36 flex-1 cursor-pointer overflow-hidden rounded-lg border border-neutral-200 bg-white text-left transition hover:border-blue-300 hover:shadow-md dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-blue-500"
                                        title="Az összes énekjavaslat megtekintése">
                                        <div class="flex h-full transition-transform duration-500 ease-in-out" :style="'transform: translateX(-' + (current * 100) + '%)'">
                                        @foreach($previews as $i => $preview)
                                        <div wire:key="suggestion-preview-{{ $celebrationIndex }}-{{ $preview['music']->id }}" class="relative flex h-full w-full shrink-0 flex-col p-3">
                                            <div class="mb-1.5 flex items-start justify-between gap-2">
                                                <div class="flex min-w-0 flex-wrap items-center gap-1">
                                                    <flux:badge color="blue" size="sm">{{ $preview['slot'] }}</flux:badge>
                                                    <x-collection-badges :music="$preview['music']" />
                                                </div>
                                                @if(count($previews) > 1)
                                                <flux:text class="shrink-0 text-xs text-neutral-400 dark:text-neutral-500">{{ $i + 1 }}/{{ count($previews) }}</flux:text>
                                                @endif
                                            </div>
                                            <flux:heading size="sm" class="truncate text-neutral-800 transition-colors group-hover/sugg:text-blue-600 dark:text-neutral-100 dark:group-hover/sugg:text-blue-400">
                                                {{ $preview['title'] }}
                                            </flux:heading>
                                            @if($preview['incipit_url'])
                                            <div class="mt-2 flex flex-1 items-center overflow-hidden">
                                                <img src="{{ $preview['incipit_url'] }}" alt="{{ $preview['title'] }}" loading="lazy" class="block h-auto max-h-14 w-auto max-w-full" />
                                            </div>
                                            @endif
                                            @if($preview['music']->genres->isNotEmpty())
                                            <div class="pointer-events-none absolute bottom-0 right-0 flex items-center justify-center gap-1 rounded-tl-md bg-gray-200/30 px-2 py-1 backdrop-blur-sm dark:bg-gray-700/30">
                                                @foreach($preview['music']->genres as $genre)
                                                    <flux:icon name="{{ $genre->icon() }}" class="h-4 w-4 flex-shrink-0 text-zinc-600 dark:text-zinc-300" />
                                                @endforeach
                                            </div>
                                            @endif
                                        </div>
                                        @endforeach
                                        </div>
                                    </div>
                                    @if(count($previews) > 1)
                                    <button type="button"
                                        x-on:click.stop="go(1)"
                                        class="flex shrink-0 items-center justify-center rounded-md px-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-blue-600 dark:text-neutral-500 dark:hover:bg-neutral-800"
                                        aria-label="Következő javaslat">
                                        <flux:icon name="chevron-right" class="h-5 w-5" />
                                    </button>
                                    @endif
                                </div>
                                <flux:button
                                    wire:click="openSuggestions({{ $celebrationIndex }})"
                                    size="sm"
                                    icon="light-bulb"
                                    variant="primary"
                                    class="w-full">
                                    Az összes énekjavaslat
                                </flux:button>
                                @elseif($hasSuggestions)
                                <flux:button
                                    wire:click="openSuggestions({{ $celebrationIndex }})"
                                    size="sm"
                                    icon="light-bulb"
                                    class="w-full">
                                    Énekjavaslatok az ünnepre
                                </flux:button>
                                @else
                                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400 italic text-center py-1">
                                    Még nincsenek énekjavaslatok{{ GenreContext::getId() ? ' ' . GenreContext::label() . ' műfajban' : '' }}.
                                </flux:text>
                                <flux:button
                                    wire:click="openSuggestions({{ $celebrationIndex }})"
                                    size="sm"
                                    icon="information-circle"
                                    class="w-full">
                                    Ünnep részletei
                                </flux:button>
                                @endif
                                @auth
                                <flux:button
                                    wire:click="createMusicPlan({{ $loop->index }})"
                                    wire:confirm="Létrehozol új énekrendet? ({{ GenreContext::getId() ? GenreContext::label() : 'Műfaj nélkül' }})"
                                    variant="filled"
                                    size="sm"
                                    icon="list-music-add"
                                    class="w-full">
                                    Új énekrend
                                </flux:button>
                                @endauth
                                @endif
                            </div>
                            <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                                <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">
                                    Más kántorok ezt énekelték:
                                </flux:heading>
                                @if($publishedPlans->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($publishedPlans as $plan)
                                    @php $planPreviews = $this->planPreviews($plan); @endphp
                                    <div class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                        <a
                                            href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}"
                                            class="flex items-center justify-between p-3 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors group">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <flux:icon name="{{ $plan->genre?->icon() ?? 'musical-note' }}" class="h-4 w-4 text-blue-600 dark:text-blue-400 shrink-0" variant="mini" />
                                                <div class="min-w-0">
                                                    <x-user-badge :user="$plan->user" />
                                                    <flux:text class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                        {{ $plan->slots_count }} rész · {{ $plan->music_assignments_count }} zenemű
                                                    </flux:text>
                                                </div>
                                            </div>
                                            <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600 shrink-0" variant="mini" />
                                        </a>
                                        @if(!empty($planPreviews))
                                        <div x-data="{
                                                current: 0,
                                                total: {{ count($planPreviews) }},
                                                go(step) { this.current = (this.current + step + this.total) % this.total; },
                                             }"
                                             wire:key="plan-preview-carousel-{{ $plan->id }}"
                                             class="flex items-stretch gap-1.5 border-t border-neutral-100 p-2 dark:border-neutral-800">
                                            @if(count($planPreviews) > 1)
                                            <button type="button"
                                                x-on:click.stop="go(-1)"
                                                class="flex shrink-0 items-center justify-center rounded-md px-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-blue-600 dark:text-neutral-500 dark:hover:bg-neutral-800"
                                                aria-label="Előző ének">
                                                <flux:icon name="chevron-left" class="h-5 w-5" />
                                            </button>
                                            @endif
                                            <a
                                                href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}"
                                                class="group/plan relative h-36 flex-1 cursor-pointer overflow-hidden rounded-lg border border-neutral-200 bg-white text-left transition hover:border-blue-300 hover:shadow-md dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-blue-500"
                                                title="Az énekrend megtekintése">
                                                <div class="flex h-full transition-transform duration-500 ease-in-out" :style="'transform: translateX(-' + (current * 100) + '%)'">
                                                @foreach($planPreviews as $i => $preview)
                                                <div wire:key="plan-preview-{{ $plan->id }}-{{ $preview['music']->id }}" class="relative flex h-full w-full shrink-0 flex-col p-3">
                                                    <div class="mb-1.5 flex items-start justify-between gap-2">
                                                        <div class="flex min-w-0 flex-wrap items-center gap-1">
                                                            <flux:badge color="blue" size="sm">{{ $preview['slot'] }}</flux:badge>
                                                            <x-collection-badges :music="$preview['music']" />
                                                        </div>
                                                        @if(count($planPreviews) > 1)
                                                        <flux:text class="shrink-0 text-xs text-neutral-400 dark:text-neutral-500">{{ $i + 1 }}/{{ count($planPreviews) }}</flux:text>
                                                        @endif
                                                    </div>
                                                    <flux:heading size="sm" class="truncate text-neutral-800 transition-colors group-hover/plan:text-blue-600 dark:text-neutral-100 dark:group-hover/plan:text-blue-400">
                                                        {{ $preview['title'] }}
                                                    </flux:heading>
                                                    @if($preview['incipit_url'])
                                                    <div class="mt-2 flex flex-1 items-center overflow-hidden">
                                                        <img src="{{ $preview['incipit_url'] }}" alt="{{ $preview['title'] }}" loading="lazy" class="block h-auto max-h-14 w-auto max-w-full" />
                                                    </div>
                                                    @endif
                                                    @if($preview['music']->genres->isNotEmpty())
                                                    <div class="pointer-events-none absolute bottom-0 right-0 flex items-center justify-center gap-1 rounded-tl-md bg-gray-200/30 px-2 py-1 backdrop-blur-sm dark:bg-gray-700/30">
                                                        @foreach($preview['music']->genres as $genre)
                                                            <flux:icon name="{{ $genre->icon() }}" class="h-4 w-4 flex-shrink-0 text-zinc-600 dark:text-zinc-300" />
                                                        @endforeach
                                                    </div>
                                                    @endif
                                                </div>
                                                @endforeach
                                                </div>
                                            </a>
                                            @if(count($planPreviews) > 1)
                                            <button type="button"
                                                x-on:click.stop="go(1)"
                                                class="flex shrink-0 items-center justify-center rounded-md px-1 text-neutral-400 transition hover:bg-neutral-100 hover:text-blue-600 dark:text-neutral-500 dark:hover:bg-neutral-800"
                                                aria-label="Következő ének">
                                                <flux:icon name="chevron-right" class="h-5 w-5" />
                                            </button>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400 italic">
                                    Még nincs megosztott énekrend{{ GenreContext::getId() ? ' ' . GenreContext::label() . ' műfajban' : '' }}.
                                </flux:text>
                                @endif
                            </div>                            
                        </div>
                    </flux:card>
                    @endforeach
                </div>
                @endif
            </div>
    </flux:card>

    <livewire:direktorium-modal />
</div>