<?php

use App\Models\MusicPlan;
use App\Models\MusicPlanSlotPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    public array $availableTemplates = [];

    public ?int $genreId = null;

    public bool $isPublished = false;

    public ?string $celebrationName = null;

    public ?string $celebrationDate = null;

    public bool $isEditingCelebration = false;

    public ?string $privateNotes = null;

    public bool $showCelebrationSelector = false;

    public array $availableCelebrations = [];

    public ?int $selectedCelebrationId = null;

    public string $celebrationSearch = '';

    public string $activeTemplateTab = 'template';

    public ?int $activeSlotPlanId = null;

    /**
     * The ordered list of MusicPlanSlotPlan pivot records for this plan.
     * Recomputed fresh on every render — no stale state to manage.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MusicPlanSlotPlan>
     */
    #[Computed]
    public function planSlots(): \Illuminate\Database\Eloquent\Collection
    {
        return MusicPlanSlotPlan::with('musicPlanSlot')
            ->whereHas('musicPlanSlot')
            ->where('music_plan_id', $this->musicPlan->id)
            ->orderBy('sequence')
            ->get();
    }

    public function mount($musicPlan = null): void
    {
        if (! $musicPlan) {
            // Redirect to music plans list since creation should happen via POST
            $this->redirectRoute('my-music-plans');

            return;
        }

        // Load existing music plan
        if (! $musicPlan instanceof MusicPlan) {
            $musicPlan = MusicPlan::findOrFail($musicPlan);
        }

        $this->authorize('update', $musicPlan);
        $this->musicPlan = $musicPlan;

        // Load celebration data if there's a custom celebration
        $customCelebration = $this->musicPlan->firstCustomCelebration();
        \Log::debug('Custom celebration for plan '.$this->musicPlan->id.': '.($customCelebration ? $customCelebration->id : 'none'));
        if ($customCelebration) {
            $this->celebrationName = $customCelebration->name;
            $this->celebrationDate = $customCelebration->actual_date->format('Y-m-d');
        }

        // Sync published state
        $this->isPublished = ! $this->musicPlan->is_private;

        $this->genreId = $this->musicPlan->genre_id;
        $this->privateNotes = $this->musicPlan->private_notes;

        // Load data for both new and existing plans
        $this->loadAvailableTemplates();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->musicPlan);

        // Delete custom celebration if present
        $customCelebration = $this->musicPlan->firstCustomCelebration();
        if ($customCelebration !== null) {
            $customCelebration->delete();
        }

        $this->musicPlan->delete();

        $this->redirectRoute('my-music-plans');
    }

    public function loadAvailableTemplates(): void
    {
        $this->availableTemplates = \App\Models\MusicPlanTemplate::active()
            ->with(['slots' => function ($query) {
                $query->orderByPivot('sequence');
            }])
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'slot_count' => $template->slots->count(),
                    'slots' => $template->slots->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'description' => $slot->description,
                            'sequence' => $slot->pivot->sequence,
                            'is_included_by_default' => $slot->pivot->is_included_by_default,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();
    }

    /**
     * Re-render the parent so the planSlots computed property reflects the new list.
     * The slot-plan child components are independent — they manage their own data.
     */
    #[On('slot-list-changed')]
    public function onSlotListChanged(): void
    {
        // Computed planSlots will refresh automatically on re-render.
    }

    #[On('slot-added')]
    public function onSlotAdded(string $slotName): void
    {
        $this->dispatch('slots-updated', message: $slotName.' hozzáadva.');
    }

    #[On('slot-created')]
    public function onSlotCreated(string $slotName): void
    {
        $this->dispatch('slots-updated', message: 'Új elem létrehozva: '.$slotName);
    }

    #[On('open-music-search')]
    public function openMusicSearch(int $slotPlanId): void
    {
        $this->activeSlotPlanId = $slotPlanId;
        $this->js("Flux.modal('music-search-shared').show()");
    }

    #[On('music-selected-editor')]
    public function onMusicSelectedEditor(int $musicId): void
    {
        if (! $this->activeSlotPlanId) {
            return;
        }

        $this->authorize('update', $this->musicPlan);

        $slotPlan = MusicPlanSlotPlan::where('id', $this->activeSlotPlanId)
            ->where('music_plan_id', $this->musicPlan->id)
            ->firstOrFail();

        $maxSequence = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $slotPlan->id)
            ->max('music_sequence');

        \App\Models\MusicPlanSlotAssignment::create([
            'music_plan_slot_plan_id' => $slotPlan->id,
            'music_id' => $musicId,
            'music_sequence' => ($maxSequence ?: 0) + 1,
        ]);

        $this->js("Flux.modal('music-search-shared').close()");
        $this->dispatch('slot-assignments-refreshed', pivotId: $slotPlan->id);
        $this->dispatch('slots-updated', message: 'Zene hozzáadva az elemhez.');
        $this->activeSlotPlanId = null;
    }

    #[On('music-added-from-suggestions')]
    public function onMusicAddedFromSuggestions(int $musicId, string $slotName, ?int $slotPlanId = null): void
    {
        // If a new slot was created, the parent re-renders and mounts a new slot-plan child.
        // If an existing slot was updated, the child refreshes itself via slot-assignments-refreshed.
        $this->dispatch('slots-updated', message: 'Zene hozzáadva: '.$slotName);
    }

    #[On('add-slot-from-template')]
    public function addSlotFromTemplate(int $templateId, int $slotId): void
    {
        $this->authorize('update', $this->musicPlan);

        $this->insertSlotAtPriorityPosition($slotId);

        $this->dispatch('slots-updated', message: 'Elem hozzáadva.');
    }

    #[On('add-slots-from-template')]
    public function addSlotsFromTemplate(int $templateId): void
    {
        $this->authorize('update', $this->musicPlan);

        $template = \App\Models\MusicPlanTemplate::with(['slots' => function ($query) {
            $query->orderByPivot('sequence');
        }])->findOrFail($templateId);

        $addedCount = 0;
        foreach ($template->slots as $slot) {
            $this->insertSlotAtPriorityPosition($slot->id);
            $addedCount++;
        }

        $this->dispatch('slots-updated', message: $addedCount.' elem hozzáadva a sablonból.');
    }

    #[On('add-default-slots-from-template')]
    public function addDefaultSlotsFromTemplate(int $templateId): void
    {
        $this->authorize('update', $this->musicPlan);

        $template = \App\Models\MusicPlanTemplate::with(['slots' => function ($query) {
            $query->orderByPivot('sequence');
        }])->findOrFail($templateId);

        $addedCount = 0;
        foreach ($template->slots as $slot) {
            if ($slot->pivot->is_included_by_default) {
                $this->insertSlotAtPriorityPosition($slot->id);
                $addedCount++;
            }
        }

        $this->dispatch('slots-updated', message: $addedCount.' elem hozzáadva a sablonból.');
    }

    private function insertSlotAtPriorityPosition(int $slotId): void
    {
        $this->musicPlan->attachSlotAtPriorityPosition($slotId);
    }

    public function updatedIsPublished(): void
    {
        $this->authorize('update', $this->musicPlan);
        $this->musicPlan->is_private = ! $this->isPublished;
        $this->musicPlan->save();
    }

    public function toggleCelebrationEditing(): void
    {
        $this->isEditingCelebration = ! $this->isEditingCelebration;
    }

    public function saveCelebration(): void
    {
        $this->authorize('update', $this->musicPlan);

        $customCelebration = $this->musicPlan->firstCustomCelebration();
        if (! $customCelebration) {
            return;
        }

        $validated = $this->validate([
            'celebrationName' => ['required', 'string', 'max:255'],
            'celebrationDate' => ['required', 'date'],
        ]);

        $customCelebration->updateWithKeyAdjustment([
            'name' => $validated['celebrationName'],
            'actual_date' => $validated['celebrationDate'],
        ]);

        $this->isEditingCelebration = false;
        $this->dispatch('slots-updated', message: 'Ünnep adatai frissítve.');
    }

    public function switchToCustomCelebration(): void
    {
        $this->authorize('update', $this->musicPlan);

        // Delete old custom celebration if present (it would become an orphan)
        $oldCelebration = $this->musicPlan->celebration;
        if ($oldCelebration !== null && $oldCelebration->is_custom) {
            $oldCelebration->delete();
        }

        // Create a new custom celebration (also sets celebration_id on the plan)
        $celebration = $this->musicPlan->createCustomCelebration(
            'Egyedi ünnep',
            now()
        );

        // Update component state
        $this->celebrationName = $celebration->name;
        $this->celebrationDate = $celebration->actual_date->format('Y-m-d');
        $this->isEditingCelebration = true;

        $this->dispatch('slots-updated', message: 'Átváltva egyedi ünnepre.');
    }

    public function switchToLiturgicalCelebration(): void
    {
        $this->authorize('update', $this->musicPlan);

        // Show celebration selector
        $this->showCelebrationSelector = true;
        $this->loadAvailableCelebrations();
    }

    public function loadAvailableCelebrations(): void
    {
        $this->availableCelebrations = \App\Models\Celebration::query()
            ->where('is_custom', false)
            ->when($this->celebrationSearch, function ($query, $search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->orderBy('actual_date', 'desc')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function ($celebration) {
                return [
                    'id' => $celebration->id,
                    'name' => $celebration->name,
                    'date' => $celebration->actual_date->format('Y. F j.'),
                    'season_text' => $celebration->season_text,
                    'year_letter' => $celebration->year_letter,
                    'year_parity' => $celebration->year_parity,
                ];
            })
            ->toArray();
    }

    public function attachLiturgicalCelebration(int $celebrationId): void
    {
        $this->authorize('update', $this->musicPlan);

        $celebration = \App\Models\Celebration::findOrFail($celebrationId);

        // Associate the selected liturgical celebration
        $this->musicPlan->celebration()->associate($celebration);
        $this->musicPlan->save();

        // Reset component state
        $this->showCelebrationSelector = false;
        $this->celebrationName = null;
        $this->celebrationDate = null;
        $this->isEditingCelebration = false;

        $this->dispatch('slots-updated', message: 'Liturgikus ünnep csatolva.');

    }

    #[On('celebration-selected')]
    public function onCelebrationSelected(int $celebrationId): void
    {
        $this->attachLiturgicalCelebration($celebrationId);
    }

    public function cancelCelebrationSelection(): void
    {
        $this->showCelebrationSelector = false;
        $this->selectedCelebrationId = null;
        $this->celebrationSearch = '';
    }

    public function updatedCelebrationSearch(): void
    {
        $this->loadAvailableCelebrations();
    }

    public function savePrivateNotes(): void
    {
        $this->authorize('update', $this->musicPlan);

        $this->musicPlan->update([
            'private_notes' => $this->privateNotes,
        ]);

        $this->dispatch('slots-updated', message: 'Privát megjegyzések mentve.');
    }
};
