<?php

namespace App\Livewire;

use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    public string $slotSearch = '';

    public array $searchResults = [];

    public ?int $selectedSlotId = null;

    public array $allSlots = [];

    public string $allSlotsSearch = '';

    public bool $filterExcludeExisting = true;

    public array $existingSlotIds = [];

    public string $newSlotName = '';

    public string $newSlotDescription = '';

    public function mount(MusicPlan $musicPlan): void
    {
        $this->musicPlan = $musicPlan;
        $this->loadExistingSlotIds();
    }

    private function loadExistingSlotIds(): void
    {
        $this->existingSlotIds = $this->musicPlan->slots()->pluck('music_plan_slot_id')->toArray();
    }

    #[On('slot-list-changed')]
    public function onSlotListChanged(): void
    {
        $this->loadExistingSlotIds();
    }

    public function updatedSlotSearch(string $value): void
    {
        if (strlen($value) < 1) {
            $this->searchResults = [];
            $this->selectedSlotId = null;

            return;
        }

        $query = MusicPlanSlot::query()
            ->where(function ($query) use ($value) {
                $query->where('name', 'ilike', "%{$value}%")
                    ->orWhere('description', 'ilike', "%{$value}%");
            })
            ->where(function ($query) {
                $query->where('is_custom', false)
                    ->orWhere('music_plan_id', $this->musicPlan->id);
            });

        if ($this->filterExcludeExisting && ! empty($this->existingSlotIds)) {
            $query->whereNotIn('id', $this->existingSlotIds);
        }

        $this->searchResults = $query
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'description' => $slot->description,
                ];
            })
            ->toArray();

        // Auto-select first result if available
        if (count($this->searchResults) > 0) {
            $this->selectedSlotId = $this->searchResults[0]['id'];
        } else {
            $this->selectedSlotId = null;
        }
    }

    public function updatedFilterExcludeExisting(): void
    {
        // If there's an active search, refresh the results
        if (strlen($this->slotSearch) >= 1) {
            $this->updatedSlotSearch($this->slotSearch);
        }
    }

    public function addSlotDirectly(int $slotId): void
    {
        $slot = MusicPlanSlot::findOrFail($slotId);

        $this->musicPlan->attachSlotAtPriorityPosition($slotId);

        // Clear search
        $this->slotSearch = '';
        $this->searchResults = [];
        $this->selectedSlotId = null;

        $this->loadExistingSlotIds();
        $this->dispatch('slot-added', slotName: $slot->name);
    }

    public function showAllSlots(): void
    {
        $this->allSlotsSearch = '';
        $this->allSlots = $this->queryAllSlots();
        $this->modal('all-slots-modal')->show();
    }

    public function updatedAllSlotsSearch(): void
    {
        $this->allSlots = $this->queryAllSlots();
    }

    private function queryAllSlots(): array
    {
        $query = MusicPlanSlot::query()
            ->where(function ($q) {
                $q->where('is_custom', false)
                    ->orWhere('music_plan_id', $this->musicPlan->id);
            });

        if ($this->filterExcludeExisting && ! empty($this->existingSlotIds)) {
            $query->whereNotIn('id', $this->existingSlotIds);
        }

        if ($this->allSlotsSearch !== '') {
            $search = $this->allSlotsSearch;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(fn ($slot) => [
                'id' => $slot->id,
                'name' => $slot->name,
                'description' => $slot->description,
            ])
            ->toArray();
    }

    public function closeAllSlotsModal(): void
    {
        $this->allSlots = [];
        $this->allSlotsSearch = '';
    }

    public function openCreateSlotModal(): void
    {
        $this->newSlotName = '';
        $this->newSlotDescription = '';
        $this->resetValidation();
        $this->modal('create-slot-modal')->show();
    }

    public function closeCreateSlotModal(): void
    {
        $this->resetValidation();
    }

    public function createCustomSlot(): void
    {
        $this->authorize('create', [MusicPlanSlot::class, $this->musicPlan]);

        $validated = $this->validate([
            'newSlotName' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                $exists = MusicPlanSlot::withoutTrashed()
                    ->where('name', $value)
                    ->where(function ($query) {
                        $query->where('is_custom', false)
                            ->orWhere('music_plan_id', $this->musicPlan->id);
                    })
                    ->exists();
                if ($exists) {
                    $fail(__('validation.unique', ['attribute' => $attribute]));
                }
            }],
            'newSlotDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot = $this->musicPlan->createCustomSlot([
            'name' => $validated['newSlotName'],
            'description' => $validated['newSlotDescription'] ?? '',
        ]);

        $this->musicPlan->attachSlotAtPriorityPosition($slot->id);

        $this->modal('create-slot-modal')->close();
        $this->loadExistingSlotIds();
        $this->dispatch('slot-created', slotName: $slot->name);
    }

    public function createCustomSlotFromSearch(): void
    {
        $this->authorize('create', [MusicPlanSlot::class, $this->musicPlan]);

        $validated = $this->validate([
            'slotSearch' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                $exists = MusicPlanSlot::withoutTrashed()
                    ->where('name', $value)
                    ->where(function ($query) {
                        $query->where('is_custom', false)
                            ->orWhere('music_plan_id', $this->musicPlan->id);
                    })
                    ->exists();
                if ($exists) {
                    $fail(__('validation.unique', ['attribute' => $attribute]));
                }
            }],
        ]);

        $slot = $this->musicPlan->createCustomSlot([
            'name' => $validated['slotSearch'],
            'description' => '',
        ]);

        $this->musicPlan->attachSlotAtPriorityPosition($slot->id);

        // Clear search
        $this->slotSearch = '';
        $this->searchResults = [];
        $this->selectedSlotId = null;

        $this->loadExistingSlotIds();
        $this->dispatch('slot-created', slotName: $slot->name);
    }
};
