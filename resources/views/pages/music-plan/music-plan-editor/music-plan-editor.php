<?php

use App\Enums\MusicScopeType;
use App\Models\MusicAssignmentFlag;
use App\Models\MusicPlan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    public array $availableTemplates = [];

    public array $planSlots = [];

    public array $existingSlotIds = [];

    public string $slotSearch = '';

    public array $searchResults = [];

    public ?int $selectedSlotId = null;

    public ?int $genreId = null;

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

    public ?string $privateNotes = null;

    public bool $showCelebrationSelector = false;

    public array $availableCelebrations = [];

    public ?int $selectedCelebrationId = null;

    public string $celebrationSearch = '';

    /** An array of flags by [assignmentId] */
    public array $flags = [];

    /** An array of scopes by [assignmentId] => [['type' => string|null, 'number' => int|null], ...] */
    public array $assignmentScopes = [];

    public bool $showCreateSlotModal = false;

    public string $newSlotName = '';

    public string $newSlotDescription = '';

    public array $newSlotCustomColumns = [];

    /** @var int|null The ID of the slot being edited, null if not editing */
    public ?int $editingSlotId = null;

    /** @var string The temporary name for the slot being edited */
    public string $editingSlotName = '';

    /** @var string The temporary description for the slot being edited */
    public string $editingSlotDescription = '';

    /**
     * Get flag options for Mary UI choices.
     */
    public function getFlagOptionsProperty(): array
    {
        return MusicAssignmentFlag::all()->map(function ($flag) {
            return [
                'id' => $flag->id,
                'name' => $flag->label(),
                'icon' => 's-'.$flag->icon(),
                'color' => $flag->color(),
            ];
        })->toArray();
    }

    /**
     * Get scope type options for dropdown.
     */
    public function getScopeTypeOptionsProperty(): array
    {
        return collect(MusicScopeType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => $type->label(),
                'abbreviation' => $type->abbreviation(),
            ];
        })->toArray();
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
                    ->with(['music', 'flags', 'scopes'])
                    ->get()
                    ->map(function ($assignment) {
                        // create the flag array for this slot's assignments
                        $this->flags[$assignment->id] = $assignment->flags->pluck('id')->toArray();
                        // build scopes array
                        $this->assignmentScopes[$assignment->id] = $assignment->scopes->map(function ($scope) {
                            return [
                                'type' => $scope->scope_type?->value,
                                'number' => $scope->scope_number,
                            ];
                        })->toArray();

                        return [
                            'id' => $assignment->id,
                            'music_id' => $assignment->music_id,
                            'music_title' => $assignment->music->title,
                            'music_subtitle' => $assignment->music->subtitle,
                            'music_custom_id' => $assignment->music->custom_id,
                            'music_sequence' => $assignment->music_sequence,
                            'notes' => $assignment->notes,
                            'scopes' => $assignment->scopes->map(function ($scope) {
                                return [
                                    'type' => $scope->scope_type,
                                    'number' => $scope->scope_number,
                                ];
                            })->toArray(),
                        ];
                    })
                    ->toArray();

                return [
                    'id' => $slot->id,
                    'pivot_id' => $slot->pivot->id,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'is_custom' => $slot->is_custom,
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

        // Delete custom celebrations attached to this music plan
        $this->musicPlan->customCelebrations()->each(function ($celebration) {
            $celebration->delete();
        });

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

    #[On('add-slot-from-template')]
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

    #[On('add-slots-from-template')]
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

    #[On('add-default-slots-from-template')]
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
        $this->dispatch('slots-updated', message: 'Elem sorrendje frissítve.');
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
        $this->musicPlan->is_private = ! $this->isPublished;
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

    public function clearRecentlyAddedSlot(): void
    {
        $this->recentlyAddedSlotName = null;
    }

    public function openCreateSlotModal(): void
    {
        $this->showCreateSlotModal = true;
        $this->newSlotName = '';
        $this->newSlotDescription = '';
        $this->newSlotCustomColumns = [];
        $this->resetValidation();
    }

    public function closeCreateSlotModal(): void
    {
        $this->showCreateSlotModal = false;
        $this->resetValidation();
    }

    public function createCustomSlot(): void
    {
        $this->authorize('create', [\App\Models\MusicPlanSlot::class, $this->musicPlan]);

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
        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: 'Új elem létrehozva: '.$slot->name);
    }

    public function startEditingSlot(int $slotId): void
    {
        $slot = \App\Models\MusicPlanSlot::find($slotId);

        if (! $slot || ! $slot->is_custom) {
            return;
        }

        $this->authorize('update', $slot);

        $this->editingSlotId = $slotId;
        $this->editingSlotName = $slot->name;
        $this->editingSlotDescription = $slot->description ?? '';
    }

    public function cancelEditingSlot(): void
    {
        $this->editingSlotId = null;
        $this->editingSlotName = '';
        $this->editingSlotDescription = '';
    }

    public function saveEditedSlot(): void
    {
        if (! $this->editingSlotId) {
            return;
        }

        $slot = \App\Models\MusicPlanSlot::find($this->editingSlotId);

        if (! $slot || ! $slot->is_custom) {
            return;
        }

        $this->authorize('update', $slot);

        $validated = $this->validate([
            'editingSlotName' => ['required', 'string', 'max:255'],
            'editingSlotDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        $slot->update([
            'name' => $validated['editingSlotName'],
            'description' => $validated['editingSlotDescription'] ?? '',
        ]);

        $this->cancelEditingSlot();
        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: 'Elem frissítve: '.$slot->name);
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

    public function syncFlags(int $assignmentId): void
    {
        $this->authorize('update', $this->musicPlan);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if (! $assignment || $assignment->music_plan_id !== $this->musicPlan->id) {
            return;
        }

        $selectedFlagIds = $this->flags[$assignmentId] ?? [];
        $assignment->flags()->sync($selectedFlagIds);
    }

    public function updateScope(int $assignmentId, ?int $scopeNumber, ?string $scopeType): void
    {
        $this->authorize('update', $this->musicPlan);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if (! $assignment || $assignment->music_plan_id !== $this->musicPlan->id) {
            return;
        }

        // Sync all scopes from assignmentScopes
        $scopes = $this->assignmentScopes[$assignmentId] ?? [];
        $assignment->scopes()->delete();
        foreach ($scopes as $scope) {
            if (! empty($scope['type']) && ! empty($scope['number'])) {
                $assignment->scopes()->create([
                    'scope_type' => $scope['type'],
                    'scope_number' => $scope['number'],
                ]);
            }
        }

        $this->loadPlanSlots();
        $this->dispatch('slots-updated', message: 'Scope updated.');
    }

    public function addScope(int $assignmentId): void
    {
        if (! isset($this->assignmentScopes[$assignmentId])) {
            $this->assignmentScopes[$assignmentId] = [];
        }
        $this->assignmentScopes[$assignmentId][] = ['type' => null, 'number' => null];
        $this->updateScope($assignmentId, null, null);
    }

    public function removeScope(int $assignmentId, int $index): void
    {
        if (isset($this->assignmentScopes[$assignmentId][$index])) {
            array_splice($this->assignmentScopes[$assignmentId], $index, 1);
            $this->updateScope($assignmentId, null, null);
        }
    }

    public function updatedAssignmentScopes($value, $key): void
    {
        $this->updateScope((int) $key, null, null);
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

    public function switchToCustomCelebration(): void
    {
        $this->authorize('update', $this->musicPlan);

        // Detach all existing celebrations
        $this->musicPlan->celebrations()->detach();

        // Create a new custom celebration
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

        // Detach all existing celebrations
        $this->musicPlan->celebrations()->detach();

        // Attach the selected liturgical celebration
        $this->musicPlan->celebrations()->attach($celebration);

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

    public function updatedFlags($value, $key)
    {
        $this->syncFlags((int) $key);
    }

    public function savePrivateNotes(): void
    {
        $this->authorize('update', $this->musicPlan);

        $this->musicPlan->update([
            'private_notes' => $this->privateNotes,
        ]);

        $this->dispatch('slots-updated', message: 'Privát megjegyzések mentve.');
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
