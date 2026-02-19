<?php

use App\Facades\GenreContext;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\Genre;
use App\Services\CelebrationSearchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public array $celebrations = [];

    public string $date;

    public bool $loading = true;

    public ?string $error = null;

    public bool $selectable = false;

    public function mount(bool $selectable = false): void
    {
        $selectable = $selectable;
        $this->date = Carbon::now()->format('Y-m-d');
        $this->fetchLiturgicalInfo();
    }

    public function fetchLiturgicalInfo(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $response = Http::timeout(10)->get("https://szentjozsefhackathon.github.io/napi-lelki-batyu/{$this->date}.json");

            if ($response->successful()) {
                $data = $response->json();
                $this->celebrations = $data['celebration'] ?? [];
            } else {
                $this->error = 'Failed to fetch liturgical information.';
            }
        } catch (\Exception $e) {
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

        // Attach celebration
        $musicPlan->celebrations()->attach($celebration->id);

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
            ->with(['user', 'genre', 'celebrations']);

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
            ->with(['user', 'genre', 'celebrations']);

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

        $query = MusicPlan::whereHas('celebrations', function ($q) use ($celebrationIds) {
            $q->whereIn('celebrations.id', $celebrationIds);
        });

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
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 dark:from-blue-800 dark:to-purple-900 p-6 text-white">
        <div class="flex flex-col md:flex-row justify-between gap-4">
            <div class="flex items-center gap-4">
                <flux:icon name="book-open-text" class="h-10 w-10 text-white/90" variant="outline" />
                <div>
                    <flux:heading size="xl" class="text-white">Liturgikus naptár és énekrendek</flux:heading>
                    <flux:text class="hidden md:block text-blue-100">Napi ünnepek, olvasmányok és ajánlott énekrendek felfedezése</flux:text>
                    <div class="pt-1 dark:border-neutral-800 hidden md:block">
                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 text-white/80">
                            Adatforrás: <a href="https://szentjozsefhackathon.github.io/napi-lelki-batyu/" class="hover:underline">Szt. József Hackathon Napi Lelki Batyu</a>
                        </flux:text>
                    </div>

                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <flux:field class="mb-0">
                    <flux:label class="text-white/90 text-sm font-medium">Dátum kiválasztása</flux:label>
                    <flux:input
                        type="date"
                        wire:model.live="date"
                        variant="outline"
                        class="bg-white/20 border-white/30 text-white placeholder-white/70"
                        max="{{ Carbon::now()->addYears(1)->format('Y-m-d') }}"
                        min="{{ Carbon::now()->subYears(10)->format('Y-m-d') }}" />
                </flux:field>
                <div class="hidden sm:block">
                    <flux:button
                        wire:click="refresh"
                        variant="outline"
                        class="bg-white hover:bg-blue-50 border-white/30 mt-6 sm:mt-8"
                        icon="arrow-path"
                        icon:variant="mini">
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
            <flux:badge color="blue" variant="solid" size="lg" class="px-4 py-2 rounded-full">
                <flux:icon name="star" class="h-4 w-4 mr-2" variant="mini" />
                {{ count($celebrations) }} ünnep
            </flux:badge>
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
                    <!-- Title with icon -->
                    <div class="flex items-start justify-between">
                        <flux:heading size="md" class="flex-1" >
                            {{ $celebration['name'] ?? 'No title' }}
                        </flux:heading>
                        @if (isset($celebration['celebrationType']))
                            <flux:icon name="tag" class="h-4 w-4 text-amber-600 dark:text-amber-400 mr-1" variant="mini" />
                            <flux:text class="text-sm font-medium">{{ $celebration['celebrationType'] }}</flux:text>
                        @endif

                    </div>

                    <!-- Celebration details grid -->
                    <div class="grid grid-cols-3 gap-2">
                        @if (isset($celebration['yearLetter']))
                        <div class="flex items-center gap-2">
                            <flux:icon name="document-text" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                            <div>
                                <flux:badge color="blue" size="sm" class="mt-1">
                                    {{ $celebration['yearLetter'] }}
                                    {{ $celebration['yearParity'] }}
                                </flux:badge>
                            </div>
                        </div>
                        @endif

                        @if (isset($celebration['seasonText']))
                        <div class="flex items-center gap-2">
                            <flux:icon name="clock" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" variant="mini" />
                            <div>
                                <flux:text class="text-sm font-medium">{{ $celebration['seasonText'] }}</flux:text>
                            </div>
                        </div>
                        @endif

                        @if (isset($celebration['colorText']))
                        <div class="flex items-center gap-2">
                            <flux:icon name="swatch" class="h-4 w-4 text-rose-600 dark:text-rose-400" variant="mini" />
                            <div>
                                <flux:text class="text-sm font-medium">{{ $celebration['colorText'] }}</flux:text>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Readings section -->
                    @if (isset($celebration['parts']) && is_array($celebration['parts']) && count($celebration['parts']) > 0)
                    <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:icon name="book-open" class="h-4 w-4 text-neutral-500 dark:text-neutral-400" variant="mini" />
                            <flux:heading size="sm">Olvasmányok</flux:heading>
                        </div>
                        <div class="space-y-0">
                            @foreach ($celebration['parts'] as $part)
                            @if (isset($part['short_title']) && isset($part['ref']))
                            <div class="flex justify-between items-center text-sm p-2 rounded-md bg-neutral-50 dark:bg-neutral-800/50">
                                <flux:text class="font-medium mr-1">{{ $part['short_title'] }}</flux:text>
                                <flux:text class="text-neutral-700 dark:text-neutral-300 text-xs">{{ $part['ref'] }}</flux:text>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- parts2 section -->
                    @if (isset($celebration['parts2']) && is_array($celebration['parts2']) && count($celebration['parts2']) > 0)
                    <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:icon name="book-open" class="h-4 w-4 text-neutral-500 dark:text-neutral-400" variant="mini" />
                            <flux:heading size="sm">
                                @if (!empty($celebration['parts2cause']))
                                {{ $celebration['parts2cause'] }}
                                @else
                                Olvasmányok
                                @endif
                            </flux:heading>
                        </div>
                        <div class="space-y-0">
                            @foreach ($celebration['parts2'] as $part)
                            @if (isset($part['short_title']) && isset($part['ref']))
                            <div class="flex justify-between items-center text-sm p-2 rounded-md bg-neutral-50 dark:bg-neutral-800/50">
                                <flux:text class="font-medium mr-1">{{ $part['short_title'] }}</flux:text>
                                <flux:text class="text-neutral-700 dark:text-neutral-300 text-xs">{{ $part['ref'] }}</flux:text>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @auth
                    @php
                    $existingPlans = $this->getExistingMusicPlans($celebration);
                    @endphp
                    @if($existingPlans->isNotEmpty())
                    <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800 space-y-2">
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">
                            Saját énekrendjeid:
                        </flux:heading>
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
                                            <flux:icon name="eye" class="inline" />
                                            @else
                                            <flux:icon name="eye-slash" class="inline" />
                                            @endif
                                        </flux:text>
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600" variant="mini" />
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @endauth

                    @php
                    $publishedPlans = $this->getPublishedMusicPlans($celebration);
                    @endphp
                    @if($publishedPlans->isNotEmpty())
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">
                            Közzétett énekrendek:
                        </flux:heading>
                        <div class="space-y-2">
                            @foreach($publishedPlans as $plan)
                            <a
                                href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}"
                                class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="{{ $plan->genre?->icon() ?? 'musical-note' }}" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                                    <div>
                                        <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $plan->actual_date->translatedFormat('Y. m. d.') }}
                                        </flux:text>
                                        <x-user-badge :user="$plan->user" />                                        
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600" variant="mini" />
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

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
                            Énekrend javaslatok
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