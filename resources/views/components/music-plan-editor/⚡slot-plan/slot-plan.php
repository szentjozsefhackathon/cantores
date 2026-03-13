<?php

namespace App\Livewire;

use App\Enums\MusicScopeType;
use App\Models\MusicAssignmentFlag;
use App\Models\MusicPlanSlotPlan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public MusicPlanSlotPlan $slotPlan;

    #[Locked]
    public bool $isFirst = false;

    #[Locked]
    public bool $isLast = false;

    #[Locked]
    public int $totalSlots = 1;

    public array $assignments = [];

    /** @var array<int, int[]> Flags by assignment ID */
    public array $flags = [];

    /** @var array<int, array<int, array{type: string|null, number: int|null}>> */
    public array $assignmentScopes = [];

    public bool $showMusicSearchModal = false;

    public ?int $assignmentToMove = null;

    public bool $showMoveAssignmentModal = false;

    public bool $isEditingSlot = false;

    public string $editingSlotName = '';

    public string $editingSlotDescription = '';

    #[Computed]
    public function flagOptions(): array
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

    #[Computed]
    public function scopeTypeOptions(): array
    {
        return collect(MusicScopeType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => $type->label(),
                'abbreviation' => $type->abbreviation(),
            ];
        })->toArray();
    }

    /**
     * Get the other slots in the same music plan (for move-assignment modal).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MusicPlanSlotPlan>
     */
    #[Computed]
    public function otherSlots(): \Illuminate\Database\Eloquent\Collection
    {
        return MusicPlanSlotPlan::with('musicPlanSlot')
            ->where('music_plan_id', $this->slotPlan->music_plan_id)
            ->where('id', '!=', $this->slotPlan->id)
            ->orderBy('sequence')
            ->get();
    }

    public function placeholder(): string
    {
        return <<<'BLADE'
        <flux:card class="p-2">
            <div class="grid grid-cols-[1fr_auto] gap-4">
                <div class="space-y-4">
                    <div class="flex items-start gap-4">
                        <flux:skeleton class="size-10 shrink-0 rounded-full" />
                        <div class="flex-1 space-y-2 pt-1">
                            <flux:skeleton class="h-4 w-40" />
                            <flux:skeleton class="h-3 w-64" />
                        </div>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <flux:skeleton class="size-6 rounded" />
                    <flux:skeleton class="size-6 rounded" />
                    <flux:skeleton class="size-6 rounded" />
                </div>
            </div>
        </flux:card>
        BLADE;
    }

    public function mount(): void
    {
        $this->slotPlan->loadMissing('musicPlanSlot');
        $this->loadAssignments();
    }

    /**
     * Livewire calls boot() on every request (mount + subsequent hydrations).
     * Ensure relationships are always present before rendering.
     */
    public function boot(): void
    {
        $this->slotPlan->loadMissing('musicPlanSlot');
    }

    private function loadAssignments(): void
    {
        $user = auth()->user();

        $dbAssignments = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $this->slotPlan->id)
            ->orderBy('music_sequence')
            ->with(['music.collections', 'music.tags', 'music.genres', 'music.authors', 'flags', 'scopes'])
            ->get();

        $this->assignments = $dbAssignments->map(function ($assignment) use ($user) {
            $this->flags[$assignment->id] = $assignment->flags->pluck('id')->toArray();

            $dbScopes = $assignment->scopes->map(function ($scope) {
                return [
                    'type' => $scope->scope_type?->value,
                    'number' => $scope->scope_number,
                ];
            })->toArray();

            $existingScopes = $this->assignmentScopes[$assignment->id] ?? [];
            $placeholders = array_filter($existingScopes, fn ($scope) => $scope['type'] === null || $scope['number'] === null);
            $mergedScopes = array_merge($dbScopes, $placeholders);

            $this->assignmentScopes[$assignment->id] = $mergedScopes;

            $music = $assignment->music;

            return [
                'id' => $assignment->id,
                'music_id' => $assignment->music_id,
                'music_title' => $music->title,
                'music_subtitle' => $music->subtitle,
                'music_custom_id' => $music->custom_id,
                'music_is_private' => $music->is_private,
                'music_sequence' => $assignment->music_sequence,
                'notes' => $assignment->notes,
                'scopes' => $mergedScopes,
                'scope_label' => $assignment->scope_label,
                'music_collections' => $music->collections->map(fn ($c) => [
                    'abbreviation' => $c->abbreviation,
                    'title' => $c->title,
                    'order_number' => $c->pivot->order_number,
                    'page_number' => $c->pivot->page_number,
                ])->toArray(),
                'music_tags' => $music->tags->map(fn ($t) => [
                    'name' => $t->name,
                    'icon' => $t->icon(),
                ])->toArray(),
                'music_genres' => $music->genres->map(fn ($g) => [
                    'icon' => $g->icon(),
                ])->toArray(),
                'music_authors' => $music->authors->map(fn ($a) => [
                    'name' => $a->name,
                ])->toArray(),
                'can_view_music' => $user === null ? (! $music->is_private) : $user->can('view', $music),
                'can_edit_music' => $user !== null && $user->can('update', $music),
            ];
        })->toArray();
    }

    // -------------------------------------------------------------------------
    // Assignment reordering — independent of parent, only this component refreshes
    // -------------------------------------------------------------------------

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
        $this->authorize('update', $this->slotPlan->musicPlan);

        $assignments = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $this->slotPlan->id)
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

        $this->loadAssignments();
        $this->dispatch('slots-updated', message: 'Zene sorrendje frissítve.');
    }

    // -------------------------------------------------------------------------
    // Assignment CRUD
    // -------------------------------------------------------------------------

    public function removeAssignment(int $assignmentId): void
    {
        $this->authorize('update', $this->slotPlan->musicPlan);

        unset($this->flags[$assignmentId]);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if ($assignment && $assignment->music_plan_slot_plan_id === $this->slotPlan->id) {
            $deletedSequence = $assignment->music_sequence;

            DB::transaction(function () use ($assignment, $deletedSequence) {
                $assignment->delete();

                \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $this->slotPlan->id)
                    ->where('music_sequence', '>', $deletedSequence)
                    ->decrement('music_sequence');
            });

            $this->loadAssignments();
            $this->dispatch('slots-updated', message: 'Zene eltávolítva.');
        }
    }

    // -------------------------------------------------------------------------
    // Flags
    // -------------------------------------------------------------------------

    public function syncFlags(int $assignmentId): void
    {
        $this->authorize('update', $this->slotPlan->musicPlan);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if (! $assignment || $assignment->music_plan_slot_plan_id !== $this->slotPlan->id) {
            return;
        }

        $assignment->flags()->sync($this->flags[$assignmentId] ?? []);
    }

    public function updatedFlags($value, $key): void
    {
        $this->syncFlags((int) $key);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function updateScope(int $assignmentId, ?int $scopeNumber, ?string $scopeType): void
    {
        $this->authorize('update', $this->slotPlan->musicPlan);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if (! $assignment || $assignment->music_plan_slot_plan_id !== $this->slotPlan->id) {
            return;
        }

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

        $this->loadAssignments();
        $this->dispatch('slots-updated', message: 'Hatókör frissítve.');
    }

    public function addScope(int $assignmentId): void
    {
        if (! isset($this->assignmentScopes[$assignmentId])) {
            $this->assignmentScopes[$assignmentId] = [];
        }
        $this->assignmentScopes[$assignmentId][] = ['type' => null, 'number' => null];
    }

    public function removeScope(int $assignmentId, int $index): void
    {
        if (isset($this->assignmentScopes[$assignmentId][$index])) {
            array_splice($this->assignmentScopes[$assignmentId], $index, 1);
            $this->updateScope($assignmentId, null, null);
        }
    }

    public function saveScope(int $assignmentId, int $index): void
    {
        $this->updateScope($assignmentId, null, null);
    }

    // -------------------------------------------------------------------------
    // Music search modal (adds music to this slot)
    // -------------------------------------------------------------------------

    public function openMusicSearchModal(): void
    {
        $this->showMusicSearchModal = true;
    }

    public function closeMusicSearchModal(): void
    {
        $this->showMusicSearchModal = false;
    }

    #[On('music-selected')]
    public function assignMusicToSlot(int $musicId): void
    {
        // Each slot-plan instance receives this event; only the one whose modal
        // is currently open should act on it.
        if (! $this->showMusicSearchModal) {
            return;
        }

        $this->authorize('update', $this->slotPlan->musicPlan);

        $maxMusicSequence = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $this->slotPlan->id)
            ->max('music_sequence');
        $musicSequence = ($maxMusicSequence ?: 0) + 1;

        \App\Models\MusicPlanSlotAssignment::create([
            'music_plan_slot_plan_id' => $this->slotPlan->id,
            'music_id' => (int) $musicId,
            'music_sequence' => $musicSequence,
        ]);

        $this->loadAssignments();
        $this->closeMusicSearchModal();
        $this->dispatch('slots-updated', message: 'Zene hozzáadva az elemhez.');
    }

    // -------------------------------------------------------------------------
    // Move assignment to another slot
    // -------------------------------------------------------------------------

    public function openMoveAssignmentModal(int $assignmentId): void
    {
        $this->assignmentToMove = $assignmentId;
        $this->showMoveAssignmentModal = true;
    }

    public function closeMoveAssignmentModal(): void
    {
        $this->showMoveAssignmentModal = false;
        $this->assignmentToMove = null;
    }

    public function moveAssignmentToSlot(int $assignmentId, int $targetSlotPlanId): void
    {
        $this->authorize('update', $this->slotPlan->musicPlan);

        $assignment = \App\Models\MusicPlanSlotAssignment::find($assignmentId);
        if (! $assignment || $assignment->music_plan_slot_plan_id !== $this->slotPlan->id) {
            return;
        }

        $targetSlotPlan = MusicPlanSlotPlan::where('id', $targetSlotPlanId)
            ->where('music_plan_id', $this->slotPlan->music_plan_id)
            ->first();

        if (! $targetSlotPlan) {
            return;
        }

        DB::transaction(function () use ($assignment, $targetSlotPlanId) {
            $maxMusicSequence = \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $targetSlotPlanId)
                ->max('music_sequence');
            $newMusicSequence = ($maxMusicSequence ?: 0) + 1;

            $deletedSequence = $assignment->music_sequence;

            $assignment->update([
                'music_plan_slot_plan_id' => $targetSlotPlanId,
                'music_sequence' => $newMusicSequence,
            ]);

            \App\Models\MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $this->slotPlan->id)
                ->where('music_sequence', '>', $deletedSequence)
                ->decrement('music_sequence');
        });

        $this->closeMoveAssignmentModal();
        $this->loadAssignments();
        $this->dispatch('slot-assignments-refreshed', pivotId: $targetSlotPlanId);
        $this->dispatch('slots-updated', message: 'Zene áthelyezve másik elembe.');
    }

    #[On('slot-assignments-refreshed')]
    public function onSlotAssignmentsRefreshed(int $pivotId): void
    {
        if ($pivotId === $this->slotPlan->id) {
            $this->loadAssignments();
        }
    }

    // -------------------------------------------------------------------------
    // Slot-level operations — dispatch slot-list-changed so parent re-renders
    // -------------------------------------------------------------------------

    public function moveSlotUp(): void
    {
        $this->reorderSlot('up');
    }

    public function moveSlotDown(): void
    {
        $this->reorderSlot('down');
    }

    private function reorderSlot(string $direction): void
    {
        $this->authorize('update', $this->slotPlan->musicPlan);

        $allSlots = MusicPlanSlotPlan::whereHas('musicPlanSlot')
            ->where('music_plan_id', $this->slotPlan->music_plan_id)
            ->orderBy('sequence')
            ->get();

        $currentIndex = $allSlots->search(fn ($s) => $s->id === $this->slotPlan->id);
        if ($currentIndex === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        if ($targetIndex < 0 || $targetIndex >= $allSlots->count()) {
            return;
        }

        $current = $allSlots[$currentIndex];
        $target = $allSlots[$targetIndex];

        DB::transaction(function () use ($current, $target) {
            $currentSeq = $current->sequence;
            $targetSeq = $target->sequence;
            $current->update(['sequence' => $targetSeq]);
            $target->update(['sequence' => $currentSeq]);
        });

        $this->dispatch('slot-list-changed');
        $this->dispatch('slots-updated', message: 'Elem sorrendje frissítve.');
    }

    public function deleteSlot(): void
    {
        $this->authorize('update', $this->slotPlan->musicPlan);

        $pivotId = $this->slotPlan->id;
        $sequence = $this->slotPlan->sequence;
        $musicPlanId = $this->slotPlan->music_plan_id;

        DB::transaction(function () use ($pivotId, $sequence, $musicPlanId) {
            DB::table('music_plan_slot_assignments')
                ->where('music_plan_slot_plan_id', $pivotId)
                ->delete();

            DB::table('music_plan_slot_plan')
                ->where('id', $pivotId)
                ->delete();

            DB::table('music_plan_slot_plan')
                ->where('music_plan_id', $musicPlanId)
                ->where('sequence', '>', $sequence)
                ->decrement('sequence');
        });

        $this->dispatch('slot-list-changed');
        $this->dispatch('slots-updated', message: 'Elem eltávolítva.');
    }

    // -------------------------------------------------------------------------
    // Slot name editing (custom slots only)
    // -------------------------------------------------------------------------

    public function startEditingSlot(): void
    {
        $slot = $this->slotPlan->musicPlanSlot;
        if (! $slot || ! $slot->is_custom) {
            return;
        }

        $this->authorize('update', $slot);

        $this->isEditingSlot = true;
        $this->editingSlotName = $slot->name;
        $this->editingSlotDescription = $slot->description ?? '';
    }

    public function cancelEditingSlot(): void
    {
        $this->isEditingSlot = false;
        $this->editingSlotName = '';
        $this->editingSlotDescription = '';
    }

    public function saveEditedSlot(): void
    {
        if (! $this->isEditingSlot) {
            return;
        }

        $slot = $this->slotPlan->musicPlanSlot;
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

        $this->slotPlan->load('musicPlanSlot');
        $this->cancelEditingSlot();
        $this->dispatch('slots-updated', message: 'Elem frissítve: '.$slot->name);
    }
};
