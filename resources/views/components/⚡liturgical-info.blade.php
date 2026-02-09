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

<flux:card class="liturgical-info p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">Liturgical Information</flux:heading>
            <flux:text class="mt-1">Select a date to view liturgical celebrations</flux:text>
        </div>
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <flux:field class="flex items-center gap-2">
                <flux:label class="text-sm font-medium">Choose Date:</flux:label>
                <flux:input
                    type="date"
                    wire:model.live="date"
                    size="sm"
                    max="{{ Carbon::now()->addYears(1)->format('Y-m-d') }}"
                    min="{{ Carbon::now()->subYears(10)->format('Y-m-d') }}"
                    class="w-40"
                />
            </flux:field>
            <flux:button
                wire:click="refresh"
                variant="primary"
                size="sm"
                :loading="$loading"
                icon="arrow-path"
                icon:variant="mini"
            >
                Refresh
            </flux:button>
        </div>
    </div>

    <div>
        <flux:badge color="blue" size="lg" class="px-4 py-2">
            {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('Y. F j.') }}
        </flux:badge>
    </div>

    @if ($loading)
        <div class="text-center py-8 space-y-4">
            <flux:icon.loading class="h-8 w-8 mx-auto text-green-600" />
            <flux:text class="text-neutral-600 dark:text-neutral-400">Loading liturgical information...</flux:text>
        </div>
    @elseif ($error)
        <flux:callout color="red" icon="exclamation-circle">
            <flux:callout.heading>Error</flux:callout.heading>
            <flux:callout.text>{{ $error }}</flux:callout.text>
        </flux:callout>
    @elseif (empty($celebrations))
        <flux:callout color="zinc" icon="information-circle">
            <flux:callout.heading>No information available</flux:callout.heading>
            <flux:callout.text>No liturgical information available for the selected date.</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid grid-cols-1 md:grid-cols-{{ min(count($celebrations), 4) }} gap-6">
            @foreach ($celebrations as $celebration)
                <flux:card class="celebration-card p-4 space-y-4">
                    <div>
                        <flux:heading size="md" class="mb-2">
                            {{ $celebration['title'] ?? 'No title' }}
                        </flux:heading>
                        <div class="space-y-3">
                            @if (isset($celebration['yearLetter']))
                                <div class="flex items-center gap-2">
                                    <flux:text class="text-sm font-medium">Year Letter:</flux:text>
                                    <flux:badge color="green" size="sm">
                                        {{ $celebration['yearLetter'] }}
                                    </flux:badge>
                                </div>
                            @endif
                            @if (isset($celebration['seasonText']))
                                <div class="flex items-center gap-2">
                                    <flux:text class="text-sm font-medium">Season:</flux:text>
                                    <flux:text class="text-sm">{{ $celebration['seasonText'] }}</flux:text>
                                </div>
                            @endif
                            @if (isset($celebration['colorText']))
                                <div class="flex items-center gap-2">
                                    <flux:text class="text-sm font-medium">Color:</flux:text>
                                    <flux:text class="text-sm">{{ $celebration['colorText'] }}</flux:text>
                                </div>
                            @endif
                            @if (isset($celebration['celebrationType']))
                                <div class="flex items-center gap-2">
                                    <flux:text class="text-sm font-medium">Type:</flux:text>
                                    <flux:text class="text-sm">{{ $celebration['celebrationType'] }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    @if (isset($celebration['parts']) && is_array($celebration['parts']) && count($celebration['parts']) > 0)
                        <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700 space-y-2">
                            <flux:heading size="sm">Readings</flux:heading>
                            <ul class="space-y-2">
                                @foreach ($celebration['parts'] as $part)
                                    @if (isset($part['short_title']) && isset($part['ref']))
                                        <li class="text-sm">
                                            <flux:text class="font-medium">{{ $part['short_title'] }}:</flux:text>
                                            <flux:text class="text-neutral-700 dark:text-neutral-300">{{ $part['ref'] }}</flux:text>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</flux:card>