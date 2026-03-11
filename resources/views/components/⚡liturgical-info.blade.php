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

    public function mount(bool $selectable = false, bool $welcome = false): void
    {
        $this->welcome = $welcome;
        $selectable = $selectable;
        $this->date = Carbon::now()->format('Y-m-d');
        $this->fetchLiturgicalInfo();
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

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->format('Y-m-d');
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
        $this->redirectRoute('music-plan-editor', ['musicPlan' => $musicPlan->id]);
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

    public function getExistingMusicPlans(array $celebrationData): \Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();
        if (! $user) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $celebrationName = $celebrationData['name'] ?? $celebrationData['title'] ?? null;
        $dateISO = $celebrationData['dateISO'] ?? $this->date;

        if (! $celebrationName) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Find the celebration first
        $celebration = Celebration::where('name', $celebrationName)
            ->where('actual_date', $dateISO)
            ->first();

        if (! $celebration) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Get music plans through the relationship
        $query = $celebration->musicPlans()
            ->where('user_id', $user->id)
            ->with(['user', 'genre', 'celebration']);

        // Filter by current genre
        $genreId = GenreContext::getId();
        if ($genreId !== null) {
            // Show plans that belong to the current genre OR have no genre (belongs to all)
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }
        // If $genreId is null, no filtering applied (show all plans)

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getPublishedMusicPlans(array $celebrationData): \Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();
        $celebrationName = $celebrationData['name'] ?? $celebrationData['title'] ?? null;
        $dateISO = $celebrationData['dateISO'] ?? $this->date;

        if (! $celebrationName) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Find the celebration first
        $celebration = Celebration::where('name', $celebrationName)
            ->where('actual_date', $dateISO)
            ->first();

        if (! $celebration) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Get published music plans
        $query = $celebration->musicPlans()
            ->where('is_private', false)
            ->with(['user', 'genre', 'celebration']);

        // Exclude the authenticated user's own plans (if logged in)
        if ($user) {
            $query->where('user_id', '!=', $user->id);
        }

        // Determine genre filter
        $genreId = GenreContext::getId();
        if ($genreId !== null) {
            // Show plans that belong to the current genre OR have no genre (belongs to all)
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }
        // If $genreId is null, no filtering applied (show all plans)

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Check if there are any music plan suggestions for the given celebration data.
     * Returns true only if there is at least one published music plan attached
     * or at least one of the authenticated user's own music plans attached.
     */
    public function hasSuggestions(array $celebrationData): bool
    {
        $criteria = [
            'name' => $celebrationData['name'] ?? $celebrationData['title'] ?? null,
            'season' => isset($celebrationData['season']) ? (int) $celebrationData['season'] : null,
            'week' => isset($celebrationData['week']) ? (int) $celebrationData['week'] : null,
            'day' => isset($celebrationData['dayofWeek']) ? (int) $celebrationData['dayofWeek'] : null,
            'readings_code' => $celebrationData['readingsId'] ?? null,
            'year_letter' => $celebrationData['yearLetter'] ?? null,
            'year_parity' => $celebrationData['yearParity'] ?? null,
        ];

        // Remove null values
        $criteria = array_filter($criteria, fn ($value) => $value !== null);

        $service = app(CelebrationSearchService::class);
        $related = $service->findRelated($criteria);

        if ($related->isEmpty()) {
            return false;
        }

        $celebrationIds = $related->pluck('id')->toArray();
        $user = Auth::user();
        $genreId = GenreContext::getId();

        $query = MusicPlan::whereIn('celebration_id', $celebrationIds);

        // Filter by genre: include plans that belong to the current genre OR have no genre
        if ($genreId !== null) {
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }

        // Include published plans OR user's own plans (if logged in)
        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            });
        } else {
            $query->where('is_private', false);
        }

        return $query->exists();
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
        $criteria = array_filter($criteria, fn ($value) => $value !== null);

        // Store criteria in session or pass as query parameters
        // For now, we'll redirect to suggestions page with query parameters
        $this->redirectRoute('suggestions', $criteria);
    }
};
?>

<flux:card class="liturgical-info p-0 overflow-hidden border-0 shadow-xl dark:shadow-neutral-900/30">
    <!-- Header with gradient -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 dark:from-indigo-900 dark:to-fuchsia-950 p-6 text-white">
        <div class="flex flex-col md:flex-row justify-between gap-4">
            <div class="flex items-center gap-4">
                <flux:icon name="book-open-text" class="h-10 w-10" variant="outline" />
                <div>
                    @if($welcome)
                    <flux:heading size="xl" class="text-white">Liturgikus énekrendek</flux:heading>
                    <flux:text class="text-blue-100">Nézd meg, mások mit énekelnek — vagy állítsd össze és oszd meg a saját énekrendedet!</flux:text>
                    @else
                    <flux:heading size="xl" class="text-white">Liturgikus naptár és énekrendek</flux:heading>
                    @endif

                </div>
            </div>
            <div class="flex flex-col gap-2">
                <div class="flex items-end gap-2">
                    <flux:field class="mb-0">
                        <flux:input
                            type="date"
                            wire:model.live="date"
                            variant="outline"
                            class="bg-white/20 border-white/30 text-white placeholder-white/70"
                            max="{{ Carbon::now()->addYears(1)->format('Y-m-d') }}"
                            min="{{ Carbon::now()->subYears(10)->format('Y-m-d') }}" />
                    </flux:field>
                    <flux:button
                        wire:click="today"
                        variant="outline"
                        icon="calendar"
                        icon:variant="mini">
                        Ma
                    </flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button
                        wire:click="nextDay"
                        variant="outline"
                        size="sm"
                        icon="arrow-right"
                        icon:variant="mini">
                        Következő nap
                    </flux:button>
                    <flux:button
                        wire:click="nextSunday"
                        variant="outline"
                        size="sm"
                        icon="forward"
                        icon:variant="mini">
                        Következő vasárnap
                    </flux:button>
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
                <div class="flex items-center">
                    <flux:icon name="external-link" class="h-3 w-3 mr-1" variant="mini" />
                    <flux:link href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ $date }}" target="_blank" class="text-xs">
                        Igenaptár
                    </flux:link>
                </div>
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
        <flux:callout color="zinc" icon="calendar-x-mark" class="border-zinc-200 dark:border-zinc-800">
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
            // Determine border color based on colorText
            $colorText = strtolower($celebration['colorText'] ?? '');
            if ($colorText === 'lila') {
            $colorTextColor = 'border-purple-500! dark:border-purple-400!';
            } elseif ($colorText === 'zöld') {
            $colorTextColor = 'border-green-500! dark:border-green-400!';
            } elseif ($colorText === 'fehér') {
            $colorTextColor = 'border-zinc-100! dark:border-zinc-100!';
            } elseif ($colorText === 'rózsaszín|lila') {
            $colorTextColor = 'border-pink-500! dark:border-pink-400!';
            } elseif ($colorText === 'rózsaszín') {
            $colorTextColor = 'border-pink-500! dark:border-pink-400!';
            } elseif ($colorText === 'piros') {
            $colorTextColor = 'border-red-500! dark:border-red-400!';
            } elseif ($colorText === 'lila' or $colorText === 'lila|fehér') {
            $colorTextColor = 'border-purple-800! dark:border-purple-700!';
            }  elseif ($colorText === 'lila|fekete') {
            $colorTextColor = 'border-zinc-900! dark:border-zinc-400!';
            }
            else {
            $colorTextColor = 'border-neutral-300! dark:border-neutral-600!';
            }
            @endphp
            <flux:card class="celebration-card p-0 overflow-hidden border-l-4 {{ $colorTextColor }} hover:shadow-lg transition-shadow duration-300">
                <div class="p-5 space-y-4">
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
                                <div class="flex items-center gap-3">
                                    <flux:icon name="{{ $plan->genre?->icon() ?? 'musical-note' }}" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                                    <div>
                                        <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $plan->actual_date->translatedFormat('Y. m. d.') }}
                                            @if(!$plan->is_private)
                                            <flux:icon name="globe" class="inline" />
                                            @else
                                            <flux:icon name="globe-lock" class="inline" />
                                            @endif
                                        </flux:text>
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600" variant="mini" />
                            </a>
                            @endforeach
                        </div>
                        @else
                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400 italic">
                            Még nincs énekrend ehhez az ünnephez.
                        </flux:text>
                        @endif
                    </div>
                    @endauth

                    @php
                    $publishedPlans = $this->getPublishedMusicPlans($celebration);
                    @endphp
                    <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">
                            Más kántorok ezt énekelték:
                        </flux:heading>
                        @if($publishedPlans->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($publishedPlans as $plan)
                            <a
                                href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}"
                                class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="{{ $plan->genre?->icon() ?? 'musical-note' }}" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                                    <div>
                                        <x-user-badge :user="$plan->user" />
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600" variant="mini" />
                            </a>
                            @endforeach
                        </div>
                        @else
                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400 italic">
                            Még nincs megosztott énekrend.
                        </flux:text>
                        @endif
                    </div>

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
                    @auth
                        <flux:button
                            wire:click="createMusicPlan({{ $loop->index }})"
                            variant="filled"
                            size="sm"
                            icon="musical-note"
                            class="w-full">
                            Énekrend létrehozása
                        </flux:button>
                    @endauth
                        @php
                        $hasSuggestions = $this->hasSuggestions($celebration);
                        @endphp
                        @if($hasSuggestions)
                        <flux:button
                            wire:click="openSuggestions({{ $loop->index }})"
                            size="sm"
                            icon="light-bulb"
                            class="w-full">
                            Énekjavaslatok az ünnepre
                        </flux:button>
                        @else
                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400 italic text-center py-1">
                            Még nincsenek énekjavaslatok.
                        </flux:text>
                        <flux:button
                            wire:click="openSuggestions({{ $loop->index }})"
                            size="sm"
                            icon="information-circle"
                            class="w-full">
                            Ünnep részletei
                        </flux:button>
                        @endif
                    @endif
                    </div>
                </div>
            </flux:card>
            @endforeach
        </div>
        @endif
    </div>
</flux:card>