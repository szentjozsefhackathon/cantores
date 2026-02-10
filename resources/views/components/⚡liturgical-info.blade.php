<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\MusicPlan;

new class extends Component
{
    public array $celebrations = [];
    public string $date;
    public bool $loading = true;
    public ?string $error = null;

    public function mount(): void
    {
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

    public function createMusicPlan(int $celebrationIndex): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        if (!isset($this->celebrations[$celebrationIndex])) {
            return;
        }

        $celebration = $this->celebrations[$celebrationIndex];

        $musicPlan = MusicPlan::create([
            'user_id' => $user->id,
            'celebration_name' => $celebration['name'] ?? $celebration['title'] ?? 'Unknown',
            'actual_date' => $celebration['dateISO'] ?? $this->date,
            'setting' => 'organist', // default
            'season' => (int) ($celebration['season'] ?? 0),
            'season_text' => $celebration['seasonText'] ?? null,
            'week' => (int) ($celebration['week'] ?? 0),
            'day' => (int) ($celebration['dayofWeek'] ?? 0),
            'readings_code' => $celebration['readingsId'] ?? null,
            'year_letter' => $celebration['yearLetter'] ?? null,
            'year_parity' => $celebration['yearParity'] ?? null,
            'is_published' => false,
        ]);

        // Redirect to MusicPlanEditor page with the created plan
        $this->redirectRoute('music-plan-editor', ['musicPlan' => $musicPlan->id]);
    }

    public function getExistingMusicPlans(array $celebration): \Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();
        if (!$user) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $celebrationName = $celebration['name'] ?? $celebration['title'] ?? null;
        $dateISO = $celebration['dateISO'] ?? $this->date;

        if (!$celebrationName) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return MusicPlan::query()
            ->where('user_id', $user->id)
            ->where('celebration_name', $celebrationName)
            ->where('actual_date', $dateISO)
            ->orderBy('created_at', 'desc')
            ->get();
    }
};
?>

<flux:card class="liturgical-info p-0 overflow-hidden border-0 shadow-xl dark:shadow-neutral-900/30">
    <!-- Header with gradient -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 dark:from-blue-800 dark:to-purple-900 p-6 text-white">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <flux:icon name="book-open-text" class="h-10 w-10 text-white/90" variant="outline" />
                <div>
                    <flux:heading size="xl" class="text-white">Liturgikus naptár és énekrendek</flux:heading>
                    <flux:text class="text-blue-100">Napi ünnepek, olvasmányok és ajánlott énekrendek felfedezése</flux:text>
                    <div class="pt-1 dark:border-neutral-800">
                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 text-white">
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
                <flux:button
                    wire:click="refresh"
                    variant="outline"
                    class="bg-white hover:bg-blue-50 border-white/30 mt-6 sm:mt-8"
                    icon="arrow-path"
                    icon:variant="mini">
                    Újratöltés
                </flux:button>

            </div>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <!-- Selected date display -->
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <flux:icon name="calendar-days" class="h-5 w-5 text-blue-600 dark:text-blue-400" variant="mini" />
                <flux:heading size="lg">
                    {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('Y. F j., l') }}
                </flux:heading>
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
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-2 gap-6">
            @foreach ($celebrations as $celebration)
            @php
            // Determine border color based on season
            $season = strtolower($celebration['seasonText'] ?? '');
            if ($season === 'advent') {
            $seasonColor = 'border-blue-500 dark:border-blue-400';
            } elseif ($season === 'christmas') {
            $seasonColor = 'border-green-500 dark:border-green-400';
            } elseif ($season === 'lent') {
            $seasonColor = 'border-purple-500 dark:border-purple-400';
            } elseif ($season === 'easter') {
            $seasonColor = 'border-yellow-500 dark:border-yellow-400';
            } elseif ($season === 'ordinary time') {
            $seasonColor = 'border-emerald-500 dark:border-emerald-400';
            } else {
            $seasonColor = 'border-neutral-300 dark:border-neutral-600';
            }
            @endphp
            <flux:card class="celebration-card p-0 overflow-hidden border-l-4 {{ $seasonColor }} hover:shadow-lg transition-shadow duration-300">
                <div class="p-5 space-y-4">
                    <!-- Title with icon -->
                    <div class="flex items-start justify-between">
                        <flux:heading size="md" class="flex-1">
                            {{ $celebration['title'] ?? 'No title' }}
                        </flux:heading>
                        <flux:icon name="bookmark" class="h-5 w-5 text-neutral-400 dark:text-neutral-500 ml-2" variant="mini" />
                    </div>

                    <!-- Celebration details grid -->
                    <div class="grid grid-cols-2 gap-3">
                        @if (isset($celebration['yearLetter']))
                        <div class="flex items-center gap-2">
                            <flux:icon name="document-text" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                            <div>
                                <flux:badge color="blue" size="sm" class="mt-1">
                                    {{ $celebration['yearLetter'] }}
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

                        @if (isset($celebration['celebrationType']))
                        <div class="flex items-center gap-2">
                            <flux:icon name="tag" class="h-4 w-4 text-amber-600 dark:text-amber-400" variant="mini" />
                            <div>
                                <flux:text class="text-sm font-medium">{{ $celebration['celebrationType'] }}</flux:text>
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
                        <div class="space-y-2">
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
                        <div class="space-y-2">
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
                            Már létező énekrendjeid ehhez az ünnephez:
                        </flux:heading>
                        <div class="space-y-2">
                            @foreach($existingPlans as $plan)
                            <a
                                href="{{ route('music-plan-editor', ['musicPlan' => $plan->id]) }}"
                                class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors group">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="musical-note" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                                    <div>
                                        <flux:text class="font-medium group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                            {{ $plan->celebration_name }}
                                        </flux:text>
                                        <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $plan->actual_date->translatedFormat('Y. m. d.') }}
                                            • {{ \App\MusicPlanSetting::tryFrom($plan->setting)?->label() ?? $plan->setting }}
                                            {{ $plan->is_published ? '• Közzétéve' : '• Privát' }}
                                        </flux:text>
                                    </div>
                                </div>
                                <flux:icon name="chevron-right" class="h-4 w-4 text-neutral-400 group-hover:text-blue-600" variant="mini" />
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <div class="pt-4 border-t border-neutral-100 dark:border-neutral-800">
                        <flux:button
                            wire:click="createMusicPlan({{ $loop->index }})"
                            variant="outline"
                            size="sm"
                            icon="musical-note"
                            class="w-full">
                            Énekrend létrehozása
                        </flux:button>
                    </div>
                    @endauth
                </div>
            </flux:card>
            @endforeach
        </div>
        @endif
    </div>
</flux:card>