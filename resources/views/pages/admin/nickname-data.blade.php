<?php

use App\Models\City;
use App\Models\FirstName;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $searchCities = '';

    public string $searchFirstNames = '';

    public $selectedCities = [];

    public $selectedFirstNames = [];

    public $csvFileCities;

    public $csvFileFirstNames;

    public bool $showDeleteUnusedCitiesModal = false;

    public bool $showDeleteUnusedFirstNamesModal = false;

    public function updatedSearchCities(): void
    {
        $this->resetPage('citiesPage');
        $this->selectedCities = [];
    }

    public function updatedSearchFirstNames(): void
    {
        $this->resetPage('firstNamesPage');
        $this->selectedFirstNames = [];
    }

    public function getCitiesProperty()
    {
        return City::query()
            ->when($this->searchCities, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate(50, pageName: 'citiesPage');
    }

    public function getFirstNamesProperty()
    {
        return FirstName::query()
            ->when($this->searchFirstNames, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->orderBy('name')
            ->paginate(50, pageName: 'firstNamesPage');
    }

    public function uploadCitiesCsv(): void
    {
        $this->validate([
            'csvFileCities' => 'required|file|mimes:csv,txt|max:1024',
        ]);

        $path = $this->csvFileCities->getRealPath();
        $file = fopen($path, 'r');

        $names = [];
        while (($line = fgetcsv($file)) !== false) {
            $name = trim($line[0] ?? '');
            if (! empty($name)) {
                $names[] = $name;
            }
        }
        fclose($file);

        $existing = City::whereIn('name', $names)->pluck('name')->all();
        $toInsert = array_values(array_unique(array_filter($names, fn ($n) => ! in_array($n, $existing))));

        $now = now();
        City::insert(array_map(fn ($n) => ['name' => $n, 'created_at' => $now, 'updated_at' => $now], $toInsert));

        $this->csvFileCities = null;
        $this->resetPage('citiesPage');

        session()->flash('message', __('Cities CSV uploaded: :inserted inserted, :skipped skipped.', [
            'inserted' => count($toInsert),
            'skipped' => count($names) - count($toInsert),
        ]));
    }

    public function uploadFirstNamesCsv(): void
    {
        $this->validate([
            'csvFileFirstNames' => 'required|file|mimes:csv,txt|max:1024',
        ]);

        $path = $this->csvFileFirstNames->getRealPath();
        $file = fopen($path, 'r');

        $rows = [];
        while (($line = fgetcsv($file)) !== false) {
            $name = trim($line[0] ?? '');
            if (empty($name)) {
                continue;
            }
            $gender = trim($line[1] ?? '');
            $allowedGenders = ['male', 'female'];
            $rows[$name] = in_array(strtolower($gender), $allowedGenders) ? strtolower($gender) : null;
        }
        fclose($file);

        $existing = FirstName::whereIn('name', array_keys($rows))->pluck('name')->all();
        $toInsert = array_filter($rows, fn ($g, $n) => ! in_array($n, $existing), ARRAY_FILTER_USE_BOTH);

        $now = now();
        FirstName::insert(array_map(
            fn ($n, $g) => ['name' => $n, 'gender' => $g, 'created_at' => $now, 'updated_at' => $now],
            array_keys($toInsert),
            array_values($toInsert)
        ));

        $this->csvFileFirstNames = null;
        $this->resetPage('firstNamesPage');

        session()->flash('message', __('First names CSV uploaded: :inserted inserted, :skipped skipped.', [
            'inserted' => count($toInsert),
            'skipped' => count($rows) - count($toInsert),
        ]));
    }

    public function deleteSelectedCities(): void
    {
        if (empty($this->selectedCities)) {
            return;
        }

        City::whereIn('id', $this->selectedCities)->delete();
        $this->selectedCities = [];
        $this->resetPage('citiesPage');

        session()->flash('message', __('Selected cities deleted.'));
    }

    public function deleteSelectedFirstNames(): void
    {
        if (empty($this->selectedFirstNames)) {
            return;
        }

        FirstName::whereIn('id', $this->selectedFirstNames)->delete();
        $this->selectedFirstNames = [];
        $this->resetPage('firstNamesPage');

        session()->flash('message', __('Selected first names deleted.'));
    }

    public function getUnusedCitiesCountProperty(): int
    {
        return City::query()
            ->whereNotIn('id', DB::table('users')->select('city_id')->whereNotNull('city_id'))
            ->count();
    }

    public function getUnusedFirstNamesCountProperty(): int
    {
        return FirstName::query()
            ->whereNotIn('id', DB::table('users')->select('first_name_id')->whereNotNull('first_name_id'))
            ->count();
    }

    public function deleteUnusedCities(): void
    {
        City::query()
            ->whereNotIn('id', DB::table('users')->select('city_id')->whereNotNull('city_id'))
            ->delete();

        $this->showDeleteUnusedCitiesModal = false;
        $this->selectedCities = [];
        $this->resetPage('citiesPage');

        session()->flash('message', __('Unused cities deleted.'));
    }

    public function deleteUnusedFirstNames(): void
    {
        FirstName::query()
            ->whereNotIn('id', DB::table('users')->select('first_name_id')->whereNotNull('first_name_id'))
            ->delete();

        $this->showDeleteUnusedFirstNamesModal = false;
        $this->selectedFirstNames = [];
        $this->resetPage('firstNamesPage');

        session()->flash('message', __('Unused first names deleted.'));
    }

    public function getSelectedCitiesAllProperty(): bool
    {
        $pageIds = $this->cities->pluck('id')->toArray();
        if (empty($pageIds)) {
            return false;
        }

        return count(array_intersect($this->selectedCities, $pageIds)) === count($pageIds);
    }

    public function setSelectedCitiesAllProperty(bool $value): void
    {
        $pageIds = $this->cities->pluck('id')->toArray();
        if ($value) {
            $this->selectedCities = array_values(array_unique(array_merge($this->selectedCities, $pageIds)));
        } else {
            $this->selectedCities = array_values(array_diff($this->selectedCities, $pageIds));
        }
    }

    public function getSelectedFirstNamesAllProperty(): bool
    {
        $pageIds = $this->firstNames->pluck('id')->toArray();
        if (empty($pageIds)) {
            return false;
        }

        return count(array_intersect($this->selectedFirstNames, $pageIds)) === count($pageIds);
    }

    public function setSelectedFirstNamesAllProperty(bool $value): void
    {
        $pageIds = $this->firstNames->pluck('id')->toArray();
        if ($value) {
            $this->selectedFirstNames = array_values(array_unique(array_merge($this->selectedFirstNames, $pageIds)));
        } else {
            $this->selectedFirstNames = array_values(array_diff($this->selectedFirstNames, $pageIds));
        }
    }
};
?>

<x-pages::admin.layout :heading="__('Nickname and city master data')">
    <div class="mt-5 space-y-8">

        <!-- Cities Section -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Cities') }}</flux:heading>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <flux:input type="file" wire:model="csvFileCities" accept=".csv,.txt" id="csvCities" />
                        <flux:button wire:click="uploadCitiesCsv" :disabled="!$csvFileCities" class="ms-2">
                            {{ __('Process') }}
                        </flux:button>
                    </div>
                    <flux:button variant="danger" wire:click="deleteSelectedCities" :disabled="empty($selectedCities)">
                        {{ __('Delete Selected') }}
                    </flux:button>
                    <flux:button variant="danger" wire:click="$set('showDeleteUnusedCitiesModal', true)" :disabled="$this->unusedCitiesCount === 0">
                        {{ __('Delete Unused') }} ({{ $this->unusedCitiesCount }})
                    </flux:button>
                </div>
            </div>

            <div class="mb-3">
                <flux:input wire:model.live.debounce.300ms="searchCities" placeholder="{{ __('Search cities...') }}" icon="magnifying-glass" clearable />
            </div>

            <div class="border rounded-lg overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-12">
                            <flux:checkbox wire:model.live="selectedCitiesAll" />
                        </flux:table.column>
                        <flux:table.column>{{ __('ID') }}</flux:table.column>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Created At') }}</flux:table.column>
                        <flux:table.column>{{ __('Updated At') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->cities as $city)
                            <flux:table.row wire:key="city-{{ $city->id }}">
                                <flux:table.cell>
                                    <flux:checkbox wire:model.live="selectedCities" value="{{ $city->id }}" />
                                </flux:table.cell>
                                <flux:table.cell>{{ $city->id }}</flux:table.cell>
                                <flux:table.cell>{{ $city->name }}</flux:table.cell>
                                <flux:table.cell>{{ $city->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $city->updated_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center py-8 text-gray-500">
                                    {{ __('No cities found.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            <div class="mt-4">
                {{ $this->cities->links() }}
            </div>
            <p class="mt-2 text-sm text-gray-600">{{ __('CSV must contain a single column with city names. Existing names are skipped.') }}</p>
        </div>

        <!-- First Names Section -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('First Names') }}</flux:heading>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <flux:input type="file" wire:model="csvFileFirstNames" accept=".csv,.txt" id="csvFirstNames" />
                        <flux:button wire:click="uploadFirstNamesCsv" :disabled="!$csvFileFirstNames" class="ms-2">
                            {{ __('Process') }}
                        </flux:button>
                    </div>
                    <flux:button variant="danger" wire:click="deleteSelectedFirstNames" :disabled="empty($selectedFirstNames)">
                        {{ __('Delete Selected') }}
                    </flux:button>
                    <flux:button variant="danger" wire:click="$set('showDeleteUnusedFirstNamesModal', true)" :disabled="$this->unusedFirstNamesCount === 0">
                        {{ __('Delete Unused') }} ({{ $this->unusedFirstNamesCount }})
                    </flux:button>
                </div>
            </div>

            <div class="mb-3">
                <flux:input wire:model.live.debounce.300ms="searchFirstNames" placeholder="{{ __('Search first names...') }}" icon="magnifying-glass" clearable />
            </div>

            <div class="border rounded-lg overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-12">
                            <flux:checkbox wire:model.live="selectedFirstNamesAll" />
                        </flux:table.column>
                        <flux:table.column>{{ __('ID') }}</flux:table.column>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Gender') }}</flux:table.column>
                        <flux:table.column>{{ __('Created At') }}</flux:table.column>
                        <flux:table.column>{{ __('Updated At') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->firstNames as $firstName)
                            <flux:table.row wire:key="firstname-{{ $firstName->id }}">
                                <flux:table.cell>
                                    <flux:checkbox wire:model.live="selectedFirstNames" value="{{ $firstName->id }}" />
                                </flux:table.cell>
                                <flux:table.cell>{{ $firstName->id }}</flux:table.cell>
                                <flux:table.cell>{{ $firstName->name }}</flux:table.cell>
                                <flux:table.cell>{{ $firstName->gender ? __(':gender', ['gender' => $firstName->gender]) : '' }}</flux:table.cell>
                                <flux:table.cell>{{ $firstName->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                                <flux:table.cell>{{ $firstName->updated_at->format('Y-m-d H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center py-8 text-gray-500">
                                    {{ __('No first names found.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            <div class="mt-4">
                {{ $this->firstNames->links() }}
            </div>
            <p class="mt-2 text-sm text-gray-600">{{ __('CSV must contain two columns: first name and gender (male/female). Gender is optional. Existing names are skipped.') }}</p>
        </div>
    </div>

    <flux:modal wire:model="showDeleteUnusedCitiesModal" size="sm">
        <flux:heading>{{ __('Delete Unused Cities') }}</flux:heading>
        <flux:subheading>
            {{ __('This will permanently delete :count cities not assigned to any user. This action cannot be undone.', ['count' => $this->unusedCitiesCount]) }}
        </flux:subheading>
        <flux:separator />
        <div class="flex gap-2">
            <flux:button variant="danger" wire:click="deleteUnusedCities">{{ __('Delete') }}</flux:button>
            <flux:button variant="ghost" wire:click="$set('showDeleteUnusedCitiesModal', false)">{{ __('Cancel') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteUnusedFirstNamesModal" size="sm">
        <flux:heading>{{ __('Delete Unused First Names') }}</flux:heading>
        <flux:subheading>
            {{ __('This will permanently delete :count first names not assigned to any user. This action cannot be undone.', ['count' => $this->unusedFirstNamesCount]) }}
        </flux:subheading>
        <flux:separator />
        <div class="flex gap-2">
            <flux:button variant="danger" wire:click="deleteUnusedFirstNames">{{ __('Delete') }}</flux:button>
            <flux:button variant="ghost" wire:click="$set('showDeleteUnusedFirstNamesModal', false)">{{ __('Cancel') }}</flux:button>
        </div>
    </flux:modal>

</x-pages::admin.layout>
