<?php

use App\Models\MusicPlan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    public array $availableTemplates = [];

    public array $planSlots = [];

    public array $expandedTemplates = [];

    public array $existingSlotIds = [];

    public string $slotSearch = '';

    public array $searchResults = [];

    public ?int $selectedSlotId = null;

    public bool $showAllSlotsModal = false;

    public array $allSlots = [];

    public ?string $recentlyAddedSlotName = null;

    public bool $filterExcludeExisting = true;

    public bool $isPublished = false;

    public ?int $selectedSlotForMusic = null;

    public bool $showMusicSearchModal = false;

    public array $slotAssignments = [];

    public ?string $celebrationName = null;

    public ?string $celebrationDate = null;

    public bool $isEditingCelebration = false;

    /** An array of flags by [assignmentId] */
    public array $flags = [];

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
        if ($customCelebration) {
            $this->celebrationName = $customCelebration->name;
            $this->celebrationDate = $customCelebration->actual_date->format('Y-m-d');
        }

        // Sync published state
        $this->isPublished = $this->musicPlan->is_published;

        // Load data for both new and existing plans
        $this->loadAvailableTemplates();
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
    }

    private function loadPlanSlots(): void
    {
        $this->planSlots = $this->musicPlan->slots()
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get()
            ->map(function ($slot) {
                // Load assignments for this slot instance in this plan (filter by pivot id)
                $assignments = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $slot->pivot->id)
                    ->orderBy('music_sequence')
                    ->with('music')
                    ->get()
                    ->map(function ($assignment) {
                        // create the flag array for this slot's assignments                     
                        $this->flags[$assignment->id] = $this->flags[$assignment->id] ?? [];
                        return [
                            'id' => $assignment->id,
                            'music_id' => $assignment->music_id,
                            'music_title' => $assignment->music->title,
                            'music_subtitle' => $assignment->music->subtitle,
                            'music_custom_id' => $assignment->music->custom_id,
                            'music_sequence' => $assignment->music_sequence,
                            'notes' => $assignment->notes,
                        ];
                    })
                    ->toArray();

                return [
                    'id' => $slot->id,
                    'pivot_id' => $slot->pivot->id,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'assignments' => $assignments,
                ];
            })
            ->toArray();
    }

    private function loadExistingSlotIds(): void
    {
        $this->existingSlotIds = $this->musicPlan->slots()->pluck('music_plan_slot_id')->toArray();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->musicPlan);

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

    public function toggleTemplate(int $templateId): void
    {
        if (in_array($templateId, $this->expandedTemplates)) {
            $this->expandedTemplates = array_diff($this->expandedTemplates, [$templateId]);
        } else {
            $this->expandedTemplates[] = $templateId;
        }
    }

    public function addSlotFromTemplate(int $templateId, int $slotId): void
    {
        $this->authorize('update', $this->musicPlan);

        // Get existing slots for this plan to determine next sequence
        $existingSlots = $this->musicPlan->slots()->count();
        $sequence = $existingSlots + 1;

        $this->musicPlan->slots()->attach($slotId, [
            'sequence' => $sequence,
        ]);

        $this->loadExistingSlotIds();
        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: 'Elem hozzáadva.');
    }

    public function addSlotsFromTemplate(int $templateId): void
    {
        $this->authorize('update', $this->musicPlan);

        $template = \App\Models\MusicPlanTemplate::with(['slots' => function ($query) {
            $query->orderByPivot('sequence');
        }])->findOrFail($templateId);

        // Get existing slots for this plan to determine next sequence
        $existingSlots = $this->musicPlan->slots()->count();
        $sequence = $existingSlots + 1;

        $addedCount = 0;
        foreach ($template->slots as $slot) {
            $this->musicPlan->slots()->attach($slot->id, [
                'sequence' => $sequence,
            ]);
            $sequence++;
            $addedCount++;
        }

        if ($addedCount > 0) {
            $this->loadExistingSlotIds();
            $this->loadPlanSlots();
        }

        $this->dispatch('slots-updated', message: $addedCount.' elem hozzáadva a sablonból.');

    }

    public function addDefaultSlotsFromTemplate(int $templateId): void
    {
        $this->authorize('update', $this->musicPlan);

        $template = \App\Models\MusicPlanTemplate::with(['slots' => function ($query) {
            $query->orderByPivot('sequence');
        }])->findOrFail($templateId);

        // Get existing slots for this plan to determine next sequence
        $existingSlots = $this->musicPlan->slots()->count();
        $sequence = $existingSlots + 1;

        $addedCount = 0;
        foreach ($template->slots as $slot) {
            if ($slot->pivot->is_included_by_default) {
                $this->musicPlan->slots()->attach($slot->id, [
                    'sequence' => $sequence,
                ]);
                $sequence++;
                $addedCount++;
            }
        }

        if ($addedCount > 0) {
            $this->loadExistingSlotIds();
            $this->loadPlanSlots();
        }

        $this->dispatch('slots-updated', message: $addedCount.' elem hozzáadva a sablonból.');
    }

    public function moveSlotUp(int $pivotId): void
    {
        $this->reorderSlot($pivotId, 'up');
    }

    public function moveSlotDown(int $pivotId): void
    {
        $this->reorderSlot($pivotId, 'down');
    }

    public function deleteSlot(int $pivotId): void
    {
        $this->authorize('update', $this->musicPlan);

        $pivot = DB::table('music_plan_slot_plan')->where('id', $pivotId)->first();

        if (! $pivot) {
            return;
        }

        $deletedSequence = $pivot->sequence;
        $musicPlanId = $pivot->music_plan_id;

        DB::transaction(function () use ($pivotId, $deletedSequence, $musicPlanId) {
            // First, delete all music assignments for this slot instance in this plan
            DB::table('music_plan_slot_assignments')
                ->where('music_plan_slot_plan_id', $pivotId)
                ->delete();

            // Then delete the slot from the plan
            DB::table('music_plan_slot_plan')->where('id', $pivotId)->delete();

            // Decrement sequence for all later slots in the same plan
            DB::table('music_plan_slot_plan')
                ->where('music_plan_id', $musicPlanId)
                ->where('sequence', '>', $deletedSequence)
                ->decrement('sequence');
        });

        $this->loadExistingSlotIds();
        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: 'Elem eltávolítva.');
    }

    private function reorderSlot(int $pivotId, string $direction): void
    {
        $slots = $this->musicPlan->slots()
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get();

        $currentIndex = $slots->search(fn ($slot) => $slot->pivot->id === $pivotId);

        if ($currentIndex === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if ($targetIndex < 0 || $targetIndex >= $slots->count()) {
            return;
        }

        $currentSlot = $slots[$currentIndex];
        $targetSlot = $slots[$targetIndex];

        DB::transaction(function () use ($currentSlot, $targetSlot) {
            $this->updatePivotSequence($currentSlot->pivot->id, $targetSlot->pivot->sequence);
            $this->updatePivotSequence($targetSlot->pivot->id, $currentSlot->pivot->sequence);
        });

        $this->loadPlanSlots();
    }

    private function updatePivotSequence(int $pivotId, int $sequence): void
    {
        $this->musicPlan->slots()
            ->newPivotStatement()
            ->where('id', $pivotId)
            ->update(['sequence' => $sequence]);
    }

    public function updatedSlotSearch(string $value): void
    {
        if (strlen($value) < 2) {
            $this->searchResults = [];
            $this->selectedSlotId = null;

            return;
        }

        $query = \App\Models\MusicPlanSlot::query()
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
        if (strlen($this->slotSearch) >= 2) {
            $this->updatedSlotSearch($this->slotSearch);
        }
    }

    public function updatedIsPublished(): void
    {
        $this->authorize('update', $this->musicPlan);
        $this->musicPlan->is_published = $this->isPublished;
        $this->musicPlan->save();
    }

    public function addSlotDirectly(int $slotId): void
    {
        $this->authorize('update', $this->musicPlan);

        $slot = \App\Models\MusicPlanSlot::findOrFail($slotId);

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
        $this->recentlyAddedSlotName = $slot->name;

        $this->loadExistingSlotIds();
        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: $slot->name.' hozzáadva.');
    }

    public function showAllSlots(): void
    {
        $query = \App\Models\MusicPlanSlot::query();

        if ($this->filterExcludeExisting && ! empty($this->existingSlotIds)) {
            $query->whereNotIn('id', $this->existingSlotIds);
        }

        $this->allSlots = $query
            ->orderBy('name')
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

    public function clearRecentlyAddedSlot(): void
    {
        $this->recentlyAddedSlotName = null;
    }

    public function openMusicSearchModal(int $pivotId): void
    {
        $this->selectedSlotForMusic = $pivotId;
        $this->showMusicSearchModal = true;
    }

    public function closeMusicSearchModal(): void
    {
        $this->showMusicSearchModal = false;
        $this->selectedSlotForMusic = null;
    }

    #[On('music-selected')]
    public function assignMusicToSlot(int $musicId): void
    {
        $this->authorize('update', $this->musicPlan);

        if (! $this->selectedSlotForMusic) {
            return;
        }

        // Get the pivot row to identify the slot instance
        $pivot = DB::table('music_plan_slot_plan')->where('id', $this->selectedSlotForMusic)->first();
        if (! $pivot) {
            return;
        }

        // Determine next music_sequence within this slot instance
        $maxMusicSequence = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $this->selectedSlotForMusic)
            ->max('music_sequence');
        $musicSequence = ($maxMusicSequence ?: 0) + 1;

        \App\Models\MusicPlanSlotAssignment::create([
            'music_plan_slot_plan_id' => $this->selectedSlotForMusic,
            'music_plan_id' => $pivot->music_plan_id,
            'music_plan_slot_id' => $pivot->music_plan_slot_id,
            'music_id' => (int) $musicId,
            'music_sequence' => $musicSequence,
        ]);

        $this->loadPlanSlots();
        $this->closeMusicSearchModal();
        $this->dispatch('slots-updated', message: 'Zene hozzáadva az elemhez.');
    }

    public function removeAssignment(int $assignmentId): void
    {
        $this->authorize('update', $this->musicPlan);

        // remove from flags
        unset($this->flags[$assignmentId]);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if ($assignment && $assignment->music_plan_id === $this->musicPlan->id) {
            $slotPlanId = $assignment->music_plan_slot_plan_id;
            $deletedSequence = $assignment->music_sequence;

            DB::transaction(function () use ($assignment, $slotPlanId, $deletedSequence) {
                $assignment->delete();

                // Shift down sequences of remaining assignments in the same slot instance
                \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $slotPlanId)
                    ->where('music_sequence', '>', $deletedSequence)
                    ->decrement('music_sequence');
            });

            $this->loadPlanSlots();
            $this->dispatch('slots-updated', message: 'Zene eltávolítva.');
        }
    }

    public function moveAssignmentUp(int $assignmentId): void
    {
        $this->reorderAssignment($assignmentId, 'up');
    }

    public function moveAssignmentDown(int $assignmentId): void
    {
        $this->reorderAssignment($assignmentId, 'down');
    }

    private function reorderAssignment(int $assignmentId, string $direction): void
    {
        $this->authorize('update', $this->musicPlan);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if (! $assignment || $assignment->music_plan_id !== $this->musicPlan->id) {
            return;
        }

        // Get all assignments for the same slot instance (same music_plan_slot_plan_id)
        $assignments = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $assignment->music_plan_slot_plan_id)
            ->orderBy('music_sequence')
            ->get();

        $currentIndex = $assignments->search(fn ($a) => $a->id === $assignmentId);
        if ($currentIndex === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        if ($targetIndex < 0 || $targetIndex >= $assignments->count()) {
            return;
        }

        $current = $assignments[$currentIndex];
        $target = $assignments[$targetIndex];

        DB::transaction(function () use ($current, $target) {
            $currentSequence = $current->music_sequence;
            $targetSequence = $target->music_sequence;
            $current->update(['music_sequence' => $targetSequence]);
            $target->update(['music_sequence' => $currentSequence]);
        });

        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: 'Zene sorrendje frissítve.');
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

    public function updatedCelebrationName(): void
    {
        // Auto-save disabled - saving only via explicit button click
        // No action needed
    }

    public function updatedCelebrationDate(): void
    {
        // Auto-save disabled - saving only via explicit button click
        // No action needed
    }
};
