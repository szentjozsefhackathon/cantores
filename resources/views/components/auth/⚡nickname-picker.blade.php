<?php

use App\Models\City;
use App\Models\FirstName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $cityId = null;

    public ?int $firstNameId = null;

    public bool $showCityModal = false;

    public string $citySearch = '';

    public int $cityPage = 1;

    public bool $showFirstNameModal = false;

    public string $firstNameSearch = '';

    public int $firstNamePage = 1;

    public int $perPage = 10;

    public function mount(?int $cityId = null, ?int $firstNameId = null): void
    {
        $this->cityId = $cityId;
        $this->firstNameId = $firstNameId;
    }

    #[Computed]
    public function selectedCity(): ?City
    {
        return $this->cityId ? City::find($this->cityId) : null;
    }

    #[Computed]
    public function selectedFirstName(): ?FirstName
    {
        return $this->firstNameId ? FirstName::find($this->firstNameId) : null;
    }

    private function citiesQuery(): Builder
    {
        return City::query()
            ->when($this->citySearch !== '', fn (Builder $q) => $q->where('name', 'ilike', '%'.$this->citySearch.'%'))
            ->orderBy('name');
    }

    private function firstNamesQuery(): Builder
    {
        return FirstName::query()
            ->when($this->firstNameSearch !== '', fn (Builder $q) => $q->where('name', 'ilike', '%'.$this->firstNameSearch.'%'))
            ->orderBy('name');
    }

    #[Computed]
    public function cityItems(): \Illuminate\Support\Collection
    {
        return $this->citiesQuery()
            ->skip(($this->cityPage - 1) * $this->perPage)
            ->take($this->perPage)
            ->get();
    }

    #[Computed]
    public function cityPageCount(): int
    {
        return max(1, (int) ceil($this->citiesQuery()->count() / $this->perPage));
    }

    #[Computed]
    public function firstNameItems(): \Illuminate\Support\Collection
    {
        return $this->firstNamesQuery()
            ->skip(($this->firstNamePage - 1) * $this->perPage)
            ->take($this->perPage)
            ->get();
    }

    #[Computed]
    public function firstNamePageCount(): int
    {
        return max(1, (int) ceil($this->firstNamesQuery()->count() / $this->perPage));
    }

    public function updatedCitySearch(): void
    {
        $this->cityPage = 1;
    }

    public function updatedFirstNameSearch(): void
    {
        $this->firstNamePage = 1;
    }

    public function openCityModal(): void
    {
        $this->citySearch = '';
        $this->cityPage = 1;
        $this->showCityModal = true;
    }

    public function openFirstNameModal(): void
    {
        $this->firstNameSearch = '';
        $this->firstNamePage = 1;
        $this->showFirstNameModal = true;
    }

    public function selectCity(int $id): void
    {
        $this->cityId = $id;
        $this->showCityModal = false;
    }

    public function selectFirstName(int $id): void
    {
        $this->firstNameId = $id;
        $this->showFirstNameModal = false;
    }

    public function prevCityPage(): void
    {
        if ($this->cityPage > 1) {
            $this->cityPage--;
        }
    }

    public function nextCityPage(): void
    {
        if ($this->cityPage < $this->cityPageCount()) {
            $this->cityPage++;
        }
    }

    public function prevFirstNamePage(): void
    {
        if ($this->firstNamePage > 1) {
            $this->firstNamePage--;
        }
    }

    public function nextFirstNamePage(): void
    {
        if ($this->firstNamePage < $this->firstNamePageCount()) {
            $this->firstNamePage++;
        }
    }

    public function randomize(): void
    {
        $pick = DB::selectOne('
            SELECT c.id AS city_id, fn.id AS first_name_id
            FROM cities c
            CROSS JOIN first_names fn
            WHERE NOT EXISTS (
                SELECT 1 FROM users u WHERE u.city_id = c.id AND u.first_name_id = fn.id
            )
            ORDER BY RANDOM()
            LIMIT 1
        ');

        if ($pick) {
            $this->cityId = $pick->city_id;
            $this->firstNameId = $pick->first_name_id;
        }
    }
};
?>

<div>
    {{-- Hidden inputs submitted with the parent form --}}
    <input type="hidden" name="city_id" value="{{ $cityId }}">
    <input type="hidden" name="first_name_id" value="{{ $firstNameId }}">

    <flux:text class="text-xs">
        {{ __("The nickname is used throughout the site to identify the work you shared with others. By default you don't have to share anything, and you can keep everything private.") }}
    </flux:text>

    <div class="flex flex-col gap-2 mt-2">
        {{-- City display + change button --}}
        <div class="flex items-end gap-2">
            <div class="flex-1 min-w-0">
                <flux:label>{{ __('City') }}</flux:label>
                <div class="mt-1 px-3 py-2 text-sm rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 truncate min-h-9">
                    {{ $this->selectedCity?->name ?? '—' }}
                </div>
            </div>
            <flux:button type="button" wire:click="openCityModal" variant="outline" size="sm">
                {{ __('Change') }}
            </flux:button>
        </div>

        {{-- First name display + change button --}}
        <div class="flex items-end gap-2">
            <div class="flex-1 min-w-0">
                <flux:label>{{ __('First name') }}</flux:label>
                <div class="mt-1 px-3 py-2 text-sm rounded-lg border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 truncate min-h-9">
                    {{ $this->selectedFirstName?->name ?? '—' }}
                </div>
            </div>
            <flux:button type="button" wire:click="openFirstNameModal" variant="outline" size="sm">
                {{ __('Change') }}
            </flux:button>
        </div>

        <div class="flex justify-center mt-1">
            <flux:button icon="dices" type="button" wire:click="randomize" variant="filled">
                {{ __('Random nickname') }}
            </flux:button>
        </div>
    </div>

    {{-- City modal --}}
    @if ($showCityModal)
        <flux:modal wire:model="showCityModal" class="max-w-md w-full">
            <flux:heading size="lg">{{ __('Select a city') }}</flux:heading>

            <div class="mt-3">
                <flux:input
                    type="search"
                    wire:model.live.debounce.300ms="citySearch"
                    :placeholder="__('Search...')"
                    icon="magnifying-glass" />
            </div>

            @php
                $cityPages = $this->cityPageCount();
            @endphp

            <div class="mt-2 divide-y divide-zinc-100 dark:divide-zinc-700 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                @forelse ($this->cityItems as $city)
                    <button
                        type="button"
                        wire:click="selectCity({{ $city->id }})"
                        class="w-full text-left px-3 py-2 text-sm transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/60 {{ $cityId === $city->id ? 'bg-blue-50 dark:bg-blue-900/30 font-medium text-blue-700 dark:text-blue-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                        {{ $city->name }}
                    </button>
                @empty
                    <p class="px-3 py-4 text-sm text-center text-zinc-500 dark:text-zinc-400">{{ __('No results') }}</p>
                @endforelse
            </div>

            @if ($cityPages > 1)
                <div class="flex items-center justify-between mt-3">
                    <flux:button type="button" wire:click="prevCityPage" variant="outline" size="sm" icon="chevron-left" :disabled="$cityPage <= 1">
                        {{ __('Previous') }}
                    </flux:button>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $cityPage }} / {{ $cityPages }}</flux:text>
                    <flux:button type="button" wire:click="nextCityPage" variant="outline" size="sm" icon-trailing="chevron-right" :disabled="$cityPage >= $cityPages">
                        {{ __('Next') }}
                    </flux:button>
                </div>
            @endif
        </flux:modal>
    @endif

    {{-- First name modal --}}
    @if ($showFirstNameModal)
        <flux:modal wire:model="showFirstNameModal" class="max-w-md w-full">
            <flux:heading size="lg">{{ __('Select a first name') }}</flux:heading>

            <div class="mt-3">
                <flux:input
                    type="search"
                    wire:model.live.debounce.300ms="firstNameSearch"
                    :placeholder="__('Search...')"
                    icon="magnifying-glass" />
            </div>

            @php
                $firstNamePages = $this->firstNamePageCount();
            @endphp

            <div class="mt-2 divide-y divide-zinc-100 dark:divide-zinc-700 max-h-72 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                @forelse ($this->firstNameItems as $firstName)
                    <button
                        type="button"
                        wire:click="selectFirstName({{ $firstName->id }})"
                        class="w-full text-left px-3 py-2 text-sm transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/60 {{ $firstNameId === $firstName->id ? 'bg-blue-50 dark:bg-blue-900/30 font-medium text-blue-700 dark:text-blue-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                        {{ $firstName->name }}
                    </button>
                @empty
                    <p class="px-3 py-4 text-sm text-center text-zinc-500 dark:text-zinc-400">{{ __('No results') }}</p>
                @endforelse
            </div>

            @if ($firstNamePages > 1)
                <div class="flex items-center justify-between mt-3">
                    <flux:button type="button" wire:click="prevFirstNamePage" variant="outline" size="sm" icon="chevron-left" :disabled="$firstNamePage <= 1">
                        {{ __('Previous') }}
                    </flux:button>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $firstNamePage }} / {{ $firstNamePages }}</flux:text>
                    <flux:button type="button" wire:click="nextFirstNamePage" variant="outline" size="sm" icon-trailing="chevron-right" :disabled="$firstNamePage >= $firstNamePages">
                        {{ __('Next') }}
                    </flux:button>
                </div>
            @endif
        </flux:modal>
    @endif
</div>