<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

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
};
?>

<div class="liturgical-info p-6 bg-white dark:bg-neutral-900 rounded-lg shadow-md border border-neutral-200 dark:border-neutral-800">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
        <div>
            <h2 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                Liturgical Information
            </h2>
            <p class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">
                Select a date to view liturgical celebrations
            </p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="date-picker" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    Choose Date:
                </label>
                <input
                    id="date-picker"
                    type="date"
                    wire:model.live="date"
                    class="px-3 py-2 border border-neutral-300 dark:border-neutral-700 rounded-md shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 dark:bg-neutral-800 dark:text-neutral-200 text-sm"
                    max="{{ Carbon::now()->addYears(1)->format('Y-m-d') }}"
                    min="{{ Carbon::now()->subYears(10)->format('Y-m-d') }}"
                />
            </div>
            <button 
                wire:click="refresh"
                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-neutral-900"
                :disabled="$wire.loading"
            >
                <span wire:loading.remove>Refresh</span>
                <span wire:loading>Loading...</span>
            </button>
        </div>
    </div>

    <div class="mb-4">
        <div class="inline-flex items-center px-4 py-2 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-lg text-sm">
            <flux:header>
                {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('Y. F j.') }}
            </flux:header>
        </div>
    </div>

    @if ($loading)
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
            <p class="mt-2 text-neutral-600 dark:text-neutral-400">Loading liturgical information...</p>
        </div>
    @elseif ($error)
        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error</h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-400">
                        <p>{{ $error }}</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif (empty($celebrations))
        <div class="text-center py-8 text-neutral-600 dark:text-neutral-400">
            <p>No liturgical information available for the selected date.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-{{ min(count($celebrations), 4) }} gap-6">
            @foreach ($celebrations as $celebration)
                <div class="celebration-card p-4 bg-neutral-50 dark:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100 mb-2">
                                {{ $celebration['title'] ?? 'No title' }}
                            </h3>
                            <div class="space-y-2 text-sm text-neutral-700 dark:text-neutral-300">
                                @if (isset($celebration['yearLetter']))
                                    <div class="flex items-center">
                                        <span class="font-medium mr-2">Year Letter:</span>
                                        <span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 rounded text-xs font-semibold">
                                            {{ $celebration['yearLetter'] }}
                                        </span>
                                    </div>
                                @endif
                                @if (isset($celebration['seasonText']))
                                    <div>
                                        <span class="font-medium">Season:</span> {{ $celebration['seasonText'] }}
                                    </div>
                                @endif
                                @if (isset($celebration['colorText']))
                                    <div>
                                        <span class="font-medium">Color:</span> {{ $celebration['colorText'] }}
                                    </div>
                                @endif
                                @if (isset($celebration['celebrationType']))
                                    <div>
                                        <span class="font-medium">Type:</span> {{ $celebration['celebrationType'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    @if (isset($celebration['parts']) && is_array($celebration['parts']) && count($celebration['parts']) > 0)
                        <div class="mt-4 pt-4 border-t border-neutral-200 dark:border-neutral-700">
                            <h4 class="text-sm font-semibold text-neutral-800 dark:text-neutral-200 mb-2">Readings</h4>
                            <ul class="space-y-2">
                                @foreach ($celebration['parts'] as $part)
                                    @if (isset($part['short_title']) && isset($part['ref']))
                                        <li class="text-sm">
                                            <span class="font-medium">{{ $part['short_title'] }}:</span>
                                            <span class="text-neutral-700 dark:text-neutral-300">{{ $part['ref'] }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>