<?php

use App\Models\City;
use App\Models\FirstName;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $cities;

    public $firstNames;

    public $selectedCities = [];

    public $selectedFirstNames = [];

    public $csvFileCities;

    public $csvFileFirstNames;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->cities = City::allCached();
        $this->firstNames = FirstName::allCached();
    }

    public function uploadCitiesCsv()
    {
        $this->validate([
            'csvFileCities' => 'required|file|mimes:csv,txt|max:1024',
        ]);

        $path = $this->csvFileCities->getRealPath();
        $file = fopen($path, 'r');

        $inserted = 0;
        $skipped = 0;

        while (($line = fgetcsv($file)) !== false) {
            $name = trim($line[0] ?? '');
            if (empty($name)) {
                continue;
            }

            // Skip if already exists
            $exists = City::where('name', $name)->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            City::create(['name' => $name]);
            $inserted++;
        }

        fclose($file);

        $this->csvFileCities = null;
        $this->loadData();

        session()->flash('message', __('Cities CSV uploaded: :inserted inserted, :skipped skipped.', [
            'inserted' => $inserted,
            'skipped' => $skipped,
        ]));
    }

    public function uploadFirstNamesCsv()
    {
        $this->validate([
            'csvFileFirstNames' => 'required|file|mimes:csv,txt|max:1024',
        ]);

        $path = $this->csvFileFirstNames->getRealPath();
        $file = fopen($path, 'r');

        $inserted = 0;
        $skipped = 0;

        while (($line = fgetcsv($file)) !== false) {
            $name = trim($line[0] ?? '');
            if (empty($name)) {
                continue;
            }

            $exists = FirstName::where('name', $name)->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            $gender = trim($line[1] ?? '');
            $allowedGenders = ['male', 'female'];
            if (! in_array(strtolower($gender), $allowedGenders)) {
                $gender = null;
            }

            FirstName::create([
                'name' => $name,
                'gender' => $gender,
            ]);
            $inserted++;
        }

        fclose($file);

        $this->csvFileFirstNames = null;
        $this->loadData();

        session()->flash('message', __('First names CSV uploaded: :inserted inserted, :skipped skipped.', [
            'inserted' => $inserted,
            'skipped' => $skipped,
        ]));
    }

    public function deleteSelectedCities()
    {
        if (empty($this->selectedCities)) {
            return;
        }

        City::whereIn('id', $this->selectedCities)->delete();
        $this->selectedCities = [];
        $this->loadData();

        session()->flash('message', __('Selected cities deleted.'));
    }

    public function deleteSelectedFirstNames()
    {
        if (empty($this->selectedFirstNames)) {
            return;
        }

        FirstName::whereIn('id', $this->selectedFirstNames)->delete();
        $this->selectedFirstNames = [];
        $this->loadData();

        session()->flash('message', __('Selected first names deleted.'));
    }

    public function getSelectedCitiesAllProperty()
    {
        if (empty($this->cities)) {
            return false;
        }

        return count($this->selectedCities) === $this->cities->count();
    }

    public function setSelectedCitiesAllProperty($value)
    {
        if ($value) {
            $this->selectedCities = $this->cities->pluck('id')->toArray();
        } else {
            $this->selectedCities = [];
        }
    }

    public function getSelectedFirstNamesAllProperty()
    {
        if (empty($this->firstNames)) {
            return false;
        }

        return count($this->selectedFirstNames) === $this->firstNames->count();
    }

    public function setSelectedFirstNamesAllProperty($value)
    {
        if ($value) {
            $this->selectedFirstNames = $this->firstNames->pluck('id')->toArray();
        } else {
            $this->selectedFirstNames = [];
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
                </div>
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
                        @forelse ($cities as $city)
                            <flux:table.row>
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
                </div>
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
                        @forelse ($firstNames as $firstName)
                            <flux:table.row>
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
            <p class="mt-2 text-sm text-gray-600">{{ __('CSV must contain two columns: first name and gender (male/female). Gender is optional. Existing names are skipped.') }}</p>
        </div>
    </div>

</x-pages::admin.layout>
