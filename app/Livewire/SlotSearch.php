<?php

namespace App\Livewire;

use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use Livewire\Component;

class SlotSearch extends Component
{
    public MusicPlan $musicPlan;

    public string $slotSearch = '';

    public array $searchResults = [];

    public ?int $selectedSlotId = null;

    public bool $showAllSlotsModal = false;

    public array $allSlots = [];

    public bool $filterExcludeExisting = true;

    public array $existingSlotIds = [];

    public bool $showCreateSlotModal = false;

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

        // Get existing slots for this plan to determine next sequence
        $existingSlots = $this->musicPlan->slots()->count();
        $sequence = $existingSlots + 1;

        $this->musicPlan->slots()->attach($slotId, [
            'sequence' => $sequence,
        ]);

        // Clear search
        $this->slotSearch = '';
        $this->searchResults = [];
        $this->selectedSlotId = null;

        $this->loadExistingSlotIds();
        $this->dispatch('slot-added', slotName: $slot->name);
    }

    public function showAllSlots(): void
    {
        $query = MusicPlanSlot::query();

        if ($this->filterExcludeExisting && ! empty($this->existingSlotIds)) {
            $query->whereNotIn('id', $this->existingSlotIds);
        }

        $this->allSlots = $query
            ->orderBy('name')
            ->where(function ($q) {
                $q->where('is_custom', false)
                    ->orWhere('music_plan_id', $this->musicPlan->id);
            })
            ->get()
            ->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'description' => $slot->description,
                ];
            })
            ->toArray();

        $this->showAllSlotsModal = true;
    }

    public function closeAllSlotsModal(): void
    {
        $this->showAllSlotsModal = false;
        $this->allSlots = [];
    }

    public function openCreateSlotModal(): void
    {
        $this->showCreateSlotModal = true;
        $this->newSlotName = '';
        $this->newSlotDescription = '';
        $this->resetValidation();
    }

    public function closeCreateSlotModal(): void
    {
        $this->showCreateSlotModal = false;
        $this->resetValidation();
    }

    public function createCustomSlot(): void
    {
        $this->authorize('create', [MusicPlanSlot::class, $this->musicPlan]);

        $validated = $this->validate([
            'newSlotName' => ['required', 'string', 'max:255'],
            'newSlotDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot = $this->musicPlan->createCustomSlot([
            'name' => $validated['newSlotName'],
            'description' => $validated['newSlotDescription'] ?? '',
        ]);

        // Attach the newly created slot to the plan
        $existingSlots = $this->musicPlan->slots()->count();
        $sequence = $existingSlots + 1;
        $this->musicPlan->slots()->attach($slot->id, [
            'sequence' => $sequence,
        ]);

        $this->closeCreateSlotModal();
        $this->loadExistingSlotIds();
        $this->dispatch('slot-created', slotName: $slot->name);
    }

    public function createCustomSlotFromSearch(): void
    {
        $this->authorize('create', [MusicPlanSlot::class, $this->musicPlan]);

        $validated = $this->validate([
            'slotSearch' => ['required', 'string', 'max:255'],
        ]);

        $slot = $this->musicPlan->createCustomSlot([
            'name' => $validated['slotSearch'],
            'description' => '',
        ]);

        // Attach the newly created slot to the plan
        $existingSlots = $this->musicPlan->slots()->count();
        $sequence = $existingSlots + 1;
        $this->musicPlan->slots()->attach($slot->id, [
            'sequence' => $sequence,
        ]);

        // Clear search
        $this->slotSearch = '';
        $this->searchResults = [];
        $this->selectedSlotId = null;

        $this->loadExistingSlotIds();
        $this->dispatch('slot-created', slotName: $slot->name);
    }

    public function render()
    {
        return view('livewire.slot-search');
    }
}
