<?php

use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function mount($musicPlan = null): void
    {
        if (! $musicPlan) {
            // Create a new music plan for the current user
            $this->musicPlan = new MusicPlan([
                'user_id' => Auth::id(),
                'is_published' => false,
                'realm_id' => null,
            ]);

            // Authorize creation instead of view
            $this->authorize('update', MusicPlan::class);

            // Save the new plan to get an ID
            $this->musicPlan->save();

        } else {
            // Load existing music plan
            if (! $musicPlan instanceof MusicPlan) {
                $musicPlan = MusicPlan::findOrFail($musicPlan);
            }

            $this->authorize('update', $musicPlan);
            $this->musicPlan = $musicPlan;
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

        $this->redirectRoute('music-plans');
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
            'music_id' => $musicId,
            'music_sequence' => $musicSequence,
        ]);

        $this->loadPlanSlots();
        $this->closeMusicSearchModal();
        $this->dispatch('slots-updated', message: 'Zene hozzáadva az elemhez.');
    }

    public function removeAssignment(int $assignmentId): void
    {
        $this->authorize('update', $this->musicPlan);

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
        if (!$assignment || $assignment->music_plan_id !== $this->musicPlan->id) {
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
};
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <div class="flex items-center gap-4 mb-4">
                <x-music-plan-setting-icon :realm="$musicPlan->realm" />
                <flux:heading size="xl">Énekrend szerkesztése</flux:heading>
            </div>

            <div class="space-y-4">
                <!-- Notification message -->
                <div class="flex justify-end">
                    <x-action-message on="slots-updated">
                        {{ __('Művelet sikeres.') }} 
                    </x-action-message>
                </div>

                <!-- Combined info grid -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Ünnep neve</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->celebration_name ?? '–' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Dátum</flux:heading>
                        <flux:text class="text-base font-semibold">
                            @if($musicPlan->actual_date)
                                {{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}
                            @else
                                –
                            @endif
                        </flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Liturgikus év</flux:heading>
                        @php
                            $firstCelebration = $musicPlan->celebrations->first();
                        @endphp
                        <flux:text class="text-base font-semibold">{{ $firstCelebration?->year_letter ?? '–' }} {{ $firstCelebration?->year_parity ? '(' . $firstCelebration->year_parity . ')' : '' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Időszak, hét, nap</flux:heading>
                        <div class="flex flex-row gap-2">
                            <flux:badge color="blue" size="sm">{{ $firstCelebration?->season_text ?? '–' }}</flux:badge>
                            <flux:badge color="green" size="sm">{{ $firstCelebration?->week ?? '–' }}.hét</flux:badge>
                            <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                        </div>
                    </div>
                </div>


                <!-- Status -->
                <div class="flex items-center justify-between pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center gap-3">
                        <flux:icon name="{{ $isPublished ? 'eye' : 'eye-slash' }}" class="h-5 w-5 {{ $isPublished ? 'text-green-500' : 'text-neutral-500' }}" variant="mini" />
                        <flux:field variant="inline" class="mb-0">
                            <flux:label>Közzététel</flux:label>
                            <flux:switch wire:model.live="isPublished" />
                        </flux:field>
                        <div class="flex items-center">
                            @if($musicPlan->actual_date)
                                <flux:icon name="external-link" class="mr-1"/>
                                <flux:link href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ $musicPlan->actual_date->format('Y-m-d') }}" target="_blank">
                                    Igenaptár
                                </flux:link>
                            @endif
                        </div>

                    </div>
                </div>

                <!-- Editor Columns -->
                <div class="pt-6 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-4 lg:col-span-1">
                            <div class="flex items-center justify-between">
                                <flux:heading size="lg">Énekrend elemei</flux:heading>
                                <flux:badge color="zinc" size="sm">{{ count($planSlots) }} elem</flux:badge>
                            </div>

                            <!-- Autocomplete search for slots -->
                            <div x-data="{ open: false }" x-on:keydown.escape="open = false" class="relative">
                                <div class="flex gap-2 items-end">
                                    <div class="flex-1 relative">
                                        <flux:heading size="sm">Elem hozzáadása</flux:heading>
                                        <flux:field variant="inline" class="mt-2 mb-2">
                                            <flux:checkbox
                                                wire:model.live="filterExcludeExisting"
                                                id="filter-exclude-existing" />
                                            <flux:label for="filter-exclude-existing">Csak még nem szereplő elemek</flux:label>
                                        </flux:field>
                                        <flux:field>
                                            <flux:input
                                                type="text"
                                                wire:model.live="slotSearch"
                                                x-on:focus="open = true"
                                                x-on:click.outside="open = false"
                                                placeholder="Írd be az elem nevét (pl. Gloria, Bevonulás)..." />
                                        </flux:field>


                                        <!-- Dropdown results -->
                                        <div x-show="open && $wire.searchResults.length > 0"
                                            x-transition
                                            class="absolute z-10 mt-1 w-full bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                            <div class="py-1">
                                                @foreach($searchResults as $result)
                                                <button
                                                    type="button"
                                                    wire:click="addSlotDirectly({{ $result['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                                    class="w-full text-left px-4 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 flex items-center justify-between">
                                                    <div>
                                                        <div class="font-medium">{{ $result['name'] }}</div>
                                                        <div class="text-sm text-neutral-600 dark:text-neutral-400">{{ $result['description'] ?: 'Nincs leírás' }}</div>
                                                    </div>
                                                    <div class="relative h-4 w-4">
                                                        <flux:icon name="plus" class="h-4 w-4 text-neutral-900 dark:text-neutral-100" wire:loading.remove wire:target="addSlotDirectly" />
                                                        <flux:icon.loading class="h-4 w-4 text-neutral-900 dark:text-neutral-100 absolute inset-0" wire:loading wire:target="addSlotDirectly" />
                                                    </div>
                                                </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <flux:button
                                        wire:click="showAllSlots"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        icon="list-bullet"
                                        variant="outline"
                                        class="self-end whitespace-nowrap">
                                        Összes elem
                                    </flux:button>
                                </div>
                            </div>

                            <!-- All Slots Modal -->
                            <flux:modal wire:model="showAllSlotsModal" size="lg">
                                <flux:heading size="lg">Összes elérhető elem</flux:heading>

                                <div class="mt-4 max-h-96 overflow-y-auto border border-neutral-200 dark:border-neutral-700 rounded-lg">
                                    @if(count($allSlots) > 0)
                                    <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                        @foreach($allSlots as $slot)
                                        <button
                                            type="button"
                                            wire:click="addSlotDirectly({{ $slot['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            class="w-full text-left p-4 hover:bg-neutral-50 dark:hover:bg-neutral-800 flex items-center justify-between">
                                            <div class="flex-1">
                                                <div class="font-medium">{{ $slot['name'] }}</div>
                                                @if($slot['description'])
                                                <div class="text-sm text-neutral-600 dark:text-neutral-400 mt-1">{{ $slot['description'] }}</div>
                                                @else
                                                <div class="text-sm text-neutral-400 dark:text-neutral-500 mt-1">Nincs leírás</div>
                                                @endif
                                            </div>
                                            <div class="relative h-5 w-5 ml-4">
                                                <flux:icon name="plus" class="h-5 w-5 text-neutral-900 dark:text-neutral-100" wire:loading.remove wire:target="addSlotDirectly" />
                                                <flux:icon.loading
                                                    class="h-5 w-5 text-neutral-900 dark:text-neutral-100 absolute inset-0"
                                                    wire:loading
                                                    wire:target="addSlotDirectly({{ $slot['id'] }})"
                                                />
                                            </div>
                                        </button>
                                        @endforeach
                                    </div>
                                    @else
                                    <div class="p-8 text-center">
                                        <flux:icon name="inbox" class="h-12 w-12 text-neutral-400 mx-auto" />
                                        <flux:heading size="md" class="mt-4">Nincs elérhető elem</flux:heading>
                                        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                                            Minden elem már hozzá van adva az énekrendhez.
                                        </flux:text>
                                    </div>
                                    @endif
                                </div>

                                <div class="mt-6 flex justify-end">
                                    <flux:button
                                        wire:click="closeAllSlotsModal"
                                        variant="outline">
                                        Bezárás
                                    </flux:button>
                                </div>
                            </flux:modal>

                            <!-- Music Search Modal -->
                            <flux:modal wire:model="showMusicSearchModal" size="lg">
                                <flux:heading size="lg">Zene keresése és hozzáadása</flux:heading>

                                <livewire:music-search selectable="true" wire:music-selected="assignMusicToSlot" />

                                <div class="mt-6 flex justify-end">
                                    <flux:button
                                        wire:click="closeMusicSearchModal"
                                        variant="outline">
                                        Bezárás
                                    </flux:button>
                                </div>
                            </flux:modal>

                            @forelse($planSlots as $slot)
                            <flux:card class="p-2 flex items-start gap-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-semibold">
                                    {{ $slot['sequence'] }}
                                </div>
                                <div class="flex-1 space-y-1">
                                    <flux:heading size="sm">{{ $slot['name'] }}</flux:heading>
                                    @if($slot['description'])
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($slot['description'], 120) }}</flux:text>
                                    @endif

                                    <!-- Assigned music -->
                                    @if(!empty($slot['assignments']))
                                    <div class="mt-3 space-y-2">
                                        @foreach($slot['assignments'] as $assignment)
                                        <div class="flex items-center justify-between bg-neutral-50 dark:bg-neutral-800 rounded-lg px-3 py-2">
                                            <div class="flex items-center gap-3">
                                                @if(count($slot['assignments']) > 1)
                                                <div class="flex flex-col gap-1">
                                                    <flux:button
                                                        wire:click="moveAssignmentUp({{ $assignment['id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                                        :disabled="$loop->first"
                                                        icon="chevron-up"
                                                        variant="outline"
                                                        size="xs" />
                                                    <flux:button
                                                        wire:click="moveAssignmentDown({{ $assignment['id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                                        :disabled="$loop->last"
                                                        icon="chevron-down"
                                                        variant="outline"
                                                        size="xs" />
                                                </div>
                                                <flux:badge>{{ $assignment['music_sequence'] }}</flux:badge>
                                                @endif
                                                <div class="flex-1">
                                                    <div class="font-medium text-sm">{{ $assignment['music_title'] }}</div>
                                                    @if($assignment['music_subtitle'])
                                                    <div class="text-xs text-neutral-600 dark:text-neutral-400">{{ $assignment['music_subtitle'] }}</div>
                                                    @endif
                                                    @if($assignment['music_custom_id'])
                                                    <div class="text-xs text-neutral-500 dark:text-neutral-500">ID: {{ $assignment['music_custom_id'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            <flux:button
                                                wire:click="removeAssignment({{ $assignment['id'] }})"
                                                wire:confirm="Biztosan eltávolítod ezt a zenét az elemből?"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="opacity-50 cursor-not-allowed"
                                                icon="trash"
                                                variant="danger"
                                                size="xs" />
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-col gap-1">
                                        <flux:button
                                            wire:click="moveSlotUp({{ $slot['pivot_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            :disabled="$loop->first"
                                            icon="chevron-up"
                                            variant="outline"
                                            size="xs" />
                                        <flux:button
                                            wire:click="moveSlotDown({{ $slot['pivot_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            :disabled="$loop->last"
                                            icon="chevron-down"
                                            variant="outline"
                                            size="xs" />
                                    </div>
                                    <div class="border-l border-neutral-300 dark:border-neutral-700 h-6"></div>
                                    <flux:button
                                        wire:click="openMusicSearchModal({{ $slot['pivot_id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        icon="plus"
                                        variant="outline"
                                        size="xs"
                                        title="Zene hozzáadása" />
                                    <flux:button
                                        wire:click="deleteSlot({{ $slot['pivot_id'] }})"
                                        wire:confirm="Biztosan eltávolítod ezt az elemet az énekrendből?"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        icon="trash"
                                        variant="danger"
                                        size="xs" />
                                </div>
                            </flux:card>
                            @empty
                            <flux:callout variant="secondary" icon="musical-note">
                                Ehhez az énekrendhez még nem adtál elemeket.
                            </flux:callout>
                            @endforelse
                        </div>

                        <div class="space-y-4">
                            <flux:heading size="lg">Elemek hozzáadása sablonból</flux:heading>

                            @if(count($availableTemplates) > 0)
                            <div class="space-y-4">
                                @foreach($availableTemplates as $template)
                                <flux:card class="overflow-hidden" size="sm">
                                    <div class="p-1 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
                                        wire:click="toggleTemplate({{ $template['id'] }})">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center gap-2">
                                                <flux:icon
                                                    name="chevron-right"
                                                    class="h-4 w-4 transition-transform duration-200 {{ in_array($template['id'], $expandedTemplates) ? 'rotate-90' : '' }}"
                                                    variant="mini" />
                                                <div>
                                                    <flux:heading size="sm" class="mb-1">{{ $template['name'] }}</flux:heading>
                                                    @if($template['description'])
                                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($template['description'], 80) }}</flux:text>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <flux:badge color="blue" size="sm">{{ $template['slot_count'] }} elem</flux:badge>
                                                <flux:button
                                                    wire:click.stop="addSlotsFromTemplate({{ $template['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                                    icon="plus"
                                                    variant="primary"
                                                    size="sm">
                                                    <span>Összes</span>
                                                </flux:button>
                                                <flux:button
                                                    wire:click.stop="addDefaultSlotsFromTemplate({{ $template['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                                    icon="plus"
                                                    variant="outline"
                                                    size="sm">
                                                    <span>Szokásos</span>
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>

                                    @if(in_array($template['id'], $expandedTemplates))
                                    <div class="border-t border-neutral-200 dark:border-neutral-800">
                                        <div class="p-4 space-y-3">
                                            @foreach($template['slots'] as $slot)
                                            <div class="flex items-center justify-between py-2 px-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <flux:badge color="zinc" size="xs">{{ $slot['sequence'] }}</flux:badge>
                                                        <flux:heading size="xs" class="font-medium">{{ $slot['name'] }}</flux:heading>
                                                        <flux:icon
                                                            name="star"
                                                            variant="{{ $slot['is_included_by_default'] ? 'solid' : 'outline' }}"
                                                            class="h-4 w-4 {{ $slot['is_included_by_default'] ? 'text-amber-500' : 'text-neutral-400' }}" />
                                                    </div>
                                                    @if($slot['description'])
                                                    <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">{{ Str::limit($slot['description'], 60) }}</flux:text>
                                                    @endif
                                                </div>
                                                <flux:button
                                                    wire:click.stop="addSlotFromTemplate({{ $template['id'] }}, {{ $slot['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                                    icon="plus"
                                                    variant="outline"
                                                    size="sm"
                                                    class="ml-4">
                                                    <span wire:target="addSlotFromTemplate({{ $template['id'] }}, {{ $slot['id'] }})">Hozzáadás</span>

                                                </flux:button>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                </flux:card>
                                @endforeach
                            </div>
                            @else
                            <flux:callout variant="secondary" icon="information-circle">
                                Nincsenek elérhető sablonok. Először hozz létre sablonokat az admin felületen.
                            </flux:callout>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                    <flux:button variant="outline" color="zinc" icon="arrow-left" href="{{ route('dashboard') }}">
                        Vissza az irányítópultra
                    </flux:button>
                    <flux:button
                        variant="danger"
                        icon="trash"
                        wire:click="delete"
                        wire:confirm="Biztosan törölni szeretnéd ezt az énekrendet? A művelet nem visszavonható.">
                        Énekrend törlése
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</div>
