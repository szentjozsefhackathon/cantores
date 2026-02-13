<?php

use App\Models\MusicPlan;
use App\Models\Realm;
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

    public function mount($musicPlan = null): void
    {
        if (!$musicPlan) {
            // Create a new music plan for the current user
            $this->musicPlan = new MusicPlan([
                'user_id' => Auth::id(),
                'is_published' => false,
                'realm_id' => null,
            ]);
            
            // Authorize creation instead of view
            $this->authorize('create', MusicPlan::class);
            
            // Save the new plan to get an ID
            $this->musicPlan->save();
            
        } else {
            // Load existing music plan
            if (!$musicPlan instanceof MusicPlan) {
                $musicPlan = MusicPlan::findOrFail($musicPlan);
            }
            
            $this->authorize('view', $musicPlan);
            $this->musicPlan = $musicPlan;
        }
        
        // Load data for both new and existing plans
        $this->loadAvailableTemplates();
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
    }

    private function loadPlanSlots(): void
    {
        $this->planSlots = $this->musicPlan->slots()
            ->orderByPivot('sequence')
            ->get()
            ->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'pivot_id' => $slot->pivot->id,
                    'sequence' => $slot->pivot->sequence,
                ];
            })
            ->toArray();
    }

    private function loadExistingSlotIds(): void
    {
        $this->existingSlotIds = $this->musicPlan->slots()->pluck('music_plan_slots.id')->toArray();
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->musicPlan);
        
        $this->musicPlan->delete();
        
        $this->redirectRoute('music-plans');
    }

    public function loadAvailableTemplates(): void
    {
        $this->availableTemplates = DB::table('music_plan_templates')
            ->select('id', 'name', 'description')
            ->orderBy('name')
            ->get()
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
        // Check if slot already exists in plan
        if (in_array($slotId, $this->existingSlotIds)) {
            return;
        }
        
        // Get the highest sequence number
        $maxSequence = $this->musicPlan->slots()->max('sequence') ?? 0;
        
        // Attach slot with next sequence
        $this->musicPlan->slots()->attach($slotId, ['sequence' => $maxSequence + 1]);
        
        // Update local state
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
        
        // Show feedback
        $slotName = DB::table('music_plan_slots')->where('id', $slotId)->value('name');
        $this->recentlyAddedSlotName = $slotName;
    }

    public function addSlotsFromTemplate(int $templateId): void
    {
        // Get all slots from template that aren't already in plan
        $templateSlots = DB::table('music_plan_template_slots')
            ->where('music_plan_template_id', $templateId)
            ->pluck('music_plan_slot_id')
            ->toArray();
        
        $newSlots = array_diff($templateSlots, $this->existingSlotIds);
        
        if (empty($newSlots)) {
            return;
        }
        
        // Get the highest sequence number
        $maxSequence = $this->musicPlan->slots()->max('sequence') ?? 0;
        
        // Attach all new slots
        $sequence = $maxSequence + 1;
        foreach ($newSlots as $slotId) {
            $this->musicPlan->slots()->attach($slotId, ['sequence' => $sequence]);
            $sequence++;
        }
        
        // Update local state
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
        
        // Show feedback
        $this->recentlyAddedSlotName = count($newSlots) . ' elem hozzáadva';
    }

    public function addDefaultSlotsFromTemplate(int $templateId): void
    {
        // Get default slots from template that aren't already in plan
        $templateSlots = DB::table('music_plan_template_slots')
            ->where('music_plan_template_id', $templateId)
            ->where('is_default', true)
            ->pluck('music_plan_slot_id')
            ->toArray();
        
        $newSlots = array_diff($templateSlots, $this->existingSlotIds);
        
        if (empty($newSlots)) {
            return;
        }
        
        // Get the highest sequence number
        $maxSequence = $this->musicPlan->slots()->max('sequence') ?? 0;
        
        // Attach all new slots
        $sequence = $maxSequence + 1;
        foreach ($newSlots as $slotId) {
            $this->musicPlan->slots()->attach($slotId, ['sequence' => $sequence]);
            $sequence++;
        }
        
        // Update local state
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
        
        // Show feedback
        $this->recentlyAddedSlotName = count($newSlots) . ' alapértelmezett elem hozzáadva';
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
        // Find the slot in planSlots
        $slotIndex = array_search($pivotId, array_column($this->planSlots, 'pivot_id'));
        
        if ($slotIndex === false) {
            return;
        }
        
        // Detach slot
        $this->musicPlan->slots()->wherePivot('id', $pivotId)->detach();
        
        // Update local state
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
    }

    private function reorderSlot(int $pivotId, string $direction): void
    {
        // Find the slot in planSlots
        $slotIndex = array_search($pivotId, array_column($this->planSlots, 'pivot_id'));
        
        if ($slotIndex === false) {
            return;
        }
        
        $currentSequence = $this->planSlots[$slotIndex]['sequence'];
        
        if ($direction === 'up' && $slotIndex > 0) {
            $targetIndex = $slotIndex - 1;
            $targetSequence = $this->planSlots[$targetIndex]['sequence'];
            
            // Swap sequences
            $this->updatePivotSequence($pivotId, $targetSequence);
            $this->updatePivotSequence($this->planSlots[$targetIndex]['pivot_id'], $currentSequence);
            
        } elseif ($direction === 'down' && $slotIndex < count($this->planSlots) - 1) {
            $targetIndex = $slotIndex + 1;
            $targetSequence = $this->planSlots[$targetIndex]['sequence'];
            
            // Swap sequences
            $this->updatePivotSequence($pivotId, $targetSequence);
            $this->updatePivotSequence($this->planSlots[$targetIndex]['pivot_id'], $currentSequence);
        }
        
        // Reload slots to reflect new order
        $this->loadPlanSlots();
    }

    private function updatePivotSequence(int $pivotId, int $sequence): void
    {
        DB::table('music_plan_music_plan_slot')
            ->where('id', $pivotId)
            ->update(['sequence' => $sequence]);
    }

    public function updatedSlotSearch(string $value): void
    {
        if (empty($value)) {
            $this->searchResults = [];
            return;
        }
        
        $query = DB::table('music_plan_slots')
            ->select('id', 'name')
            ->where('name', 'like', '%' . $value . '%')
            ->orderBy('name')
            ->limit(10);
        
        if ($this->filterExcludeExisting && !empty($this->existingSlotIds)) {
            $query->whereNotIn('id', $this->existingSlotIds);
        }
        
        $this->searchResults = $query->get()->toArray();
    }

    public function updatedFilterExcludeExisting(): void
    {
        $this->updatedSlotSearch($this->slotSearch);
    }

    public function addSlotDirectly(int $slotId): void
    {
        // Check if slot already exists in plan
        if (in_array($slotId, $this->existingSlotIds)) {
            return;
        }
        
        // Get the highest sequence number
        $maxSequence = $this->musicPlan->slots()->max('sequence') ?? 0;
        
        // Attach slot with next sequence
        $this->musicPlan->slots()->attach($slotId, ['sequence' => $maxSequence + 1]);
        
        // Update local state
        $this->loadPlanSlots();
        $this->loadExistingSlotIds();
        
        // Show feedback
        $slotName = DB::table('music_plan_slots')->where('id', $slotId)->value('name');
        $this->recentlyAddedSlotName = $slotName;
        
        // Clear search
        $this->slotSearch = '';
        $this->searchResults = [];
    }

    public function showAllSlots(): void
    {
        $query = DB::table('music_plan_slots')
            ->select('id', 'name')
            ->orderBy('name');
        
        if ($this->filterExcludeExisting && !empty($this->existingSlotIds)) {
            $query->whereNotIn('id', $this->existingSlotIds);
        }
        
        $this->allSlots = $query->get()->toArray();
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
};
?>

<div>
    <x-slot name="header">
        <flux:heading size="xl">Énekrend szerkesztése</flux:heading>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <div class="flex items-center gap-4 mb-4">
                <x-music-plan-setting-icon :setting="$musicPlan->realm" />
                <flux:heading size="xl">Énekrend szerkesztése</flux:heading>
            </div>

            <div class="space-y-4">
                <!-- Notification message -->
                <div class="flex justify-end">
                    @if ($recentlyAddedSlotName)
                    <flux:callout color="green" icon="check-circle" class="border-green-200 dark:border-green-800">
                        <flux:callout.text>
                            "{{ $recentlyAddedSlotName }}" hozzáadva az énekrendhez.
                            <flux:button wire:click="clearRecentlyAddedSlot" variant="ghost" size="sm" class="ml-2">OK</flux:button>
                        </flux:callout.text>
                    </flux:callout>
                    @endif
                </div>

                <!-- Celebration info -->
                @if ($musicPlan->celebrations->isNotEmpty())
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon name="calendar-days" class="h-5 w-5 text-blue-600 dark:text-blue-400" variant="mini" />
                            <div>
                                <flux:heading size="md" class="text-blue-800 dark:text-blue-300">
                                    {{ $musicPlan->celebration_name ?? '–' }}
                                </flux:heading>
                                @if ($musicPlan->actual_date)
                                <flux:text class="text-blue-700 dark:text-blue-400">
                                    {{ $musicPlan->actual_date->translatedFormat('Y. F j., l') }}
                                </flux:text>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge color="blue" size="sm">
                                {{ $musicPlan->realm?->label() ?? $musicPlan->setting }}
                            </flux:badge>
                            <flux:badge color="{{ $musicPlan->is_published ? 'green' : 'zinc' }}" size="sm">
                                {{ $musicPlan->is_published ? 'Közzétéve' : 'Privát' }}
                            </flux:badge>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Current slots -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">Énekrend elemei</flux:heading>
                        <div class="flex items-center gap-2">
                            <flux:button
                                wire:click="showAllSlots"
                                variant="outline"
                                size="sm"
                                icon="plus">
                                Összes elem
                            </flux:button>
                        </div>
                    </div>

                    @if (empty($planSlots))
                    <flux:callout color="zinc" icon="information-circle" class="border-zinc-200 dark:border-zinc-800">
                        <flux:callout.heading>Üres énekrend</flux:callout.heading>
                        <flux:callout.text>Még nincsenek elemek az énekrendben. Használd a sablonokat vagy keress elemeket a keresővel.</flux:callout.text>
                    </flux:callout>
                    @else
                    <div class="space-y-2">
                        @foreach ($planSlots as $slot)
                        <div class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                            <div class="flex items-center gap-3">
                                <flux:icon name="musical-note" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                                <flux:text class="font-medium">{{ $slot['name'] }}</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    wire:click="moveSlotUp({{ $slot['pivot_id'] }})"
                                    variant="ghost"
                                    size="sm"
                                    icon="chevron-up"
                                    :disabled="$loop->first">
                                </flux:button>
                                <flux:button
                                    wire:click="moveSlotDown({{ $slot['pivot_id'] }})"
                                    variant="ghost"
                                    size="sm"
                                    icon="chevron-down"
                                    :disabled="$loop->last">
                                </flux:button>
                                <flux:button
                                    wire:click="deleteSlot({{ $slot['pivot_id'] }})"
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    color="red">
                                </flux:button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Search for slots -->
                <div class="space-y-3">
                    <flux:heading size="lg">Elem keresése</flux:heading>
                    
                    <div class="flex flex-col sm:flex-row gap-3">
                        <flux:field class="flex-1">
                            <flux:input
                                wire:model.live="slotSearch"
                                placeholder="Keresés elemek között..."
                                icon="magnifying-glass" />
                        </flux:field>
                        
                        <flux:field class="sm:w-auto">
                            <flux:checkbox
                                wire:model.live="filterExcludeExisting"
                                label="Csak még nem hozzáadott elemek" />
                        </flux:field>
                    </div>

                    @if (!empty($searchResults))
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach ($searchResults as $result)
                        <div class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                            <flux:text class="font-medium">{{ $result->name }}</flux:text>
                            <flux:button
                                wire:click="addSlotDirectly({{ $result->id }})"
                                variant="ghost"
                                size="sm"
                                icon="plus">
                            </flux:button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Templates -->
                <div class="space-y-3">
                    <flux:heading size="lg">Sablonok</flux:heading>
                    
                    @if (empty($availableTemplates))
                    <flux:callout color="zinc" icon="information-circle" class="border-zinc-200 dark:border-zinc-800">
                        <flux:callout.heading>Nincsenek sablonok</flux:callout.heading>
                        <flux:callout.text>Jelenleg nincsenek elérhető sablonok.</flux:callout.text>
                    </flux:callout>
                    @else
                    <div class="space-y-3">
                        @foreach ($availableTemplates as $template)
                        <div class="border border-neutral-200 dark:border-neutral-700 rounded-lg overflow-hidden">
                            <div class="flex items-center justify-between p-4 bg-neutral-50 dark:bg-neutral-800/50">
                                <div>
                                    <flux:heading size="md">{{ $template->name }}</flux:heading>
                                    @if ($template->description)
                                    <flux:text class="text-neutral-600 dark:text-neutral-400">{{ $template->description }}</flux:text>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        wire:click="toggleTemplate({{ $template->id }})"
                                        variant="outline"
                                        size="sm"
                                        :icon="in_array($template->id, $expandedTemplates) ? 'chevron-up' : 'chevron-down'">
                                        {{ in_array($template->id, $expandedTemplates) ? 'Összecsukás' : 'Megnyitás' }}
                                    </flux:button>
                                    <flux:button
                                        wire:click="addDefaultSlotsFromTemplate({{ $template->id }})"
                                        variant="outline"
                                        size="sm"
                                        icon="plus">
                                        Alapértelmezettek
                                    </flux:button>
                                    <flux:button
                                        wire:click="addSlotsFromTemplate({{ $template->id }})"
                                        variant="solid"
                                        size="sm"
                                        icon="plus">
                                        Összes
                                    </flux:button>
                                </div>
                            </div>
                            
                            @if (in_array($template->id, $expandedTemplates))
                            <div class="p-4 border-t border-neutral-200 dark:border-neutral-700">
                                @php
                                $templateSlots = DB::table('music_plan_template_slots')
                                    ->join('music_plan_slots', 'music_plan_template_slots.music_plan_slot_id', '=', 'music_plan_slots.id')
                                    ->where('music_plan_template_id', $template->id)
                                    ->select('music_plan_slots.id', 'music_plan_slots.name', 'music_plan_template_slots.is_default')
                                    ->orderBy('music_plan_slots.name')
                                    ->get();
                                @endphp
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach ($templateSlots as $slot)
                                    <div class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ $slot->name }}</flux:text>
                                            @if ($slot->is_default)
                                            <flux:badge color="green" size="xs">Alap</flux:badge>
                                            @endif
                                        </div>
                                        <flux:button
                                            wire:click="addSlotFromTemplate({{ $template->id }}, {{ $slot->id }})"
                                            variant="ghost"
                                            size="sm"
                                            icon="plus"
                                            :disabled="in_array($slot->id, $existingSlotIds)">
                                        </flux:button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row justify-between gap-4 pt-6 border-t border-neutral-200 dark:border-neutral-700">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <flux:button
                            wire:click="$dispatch('openModal', { component: 'music-plan-settings', arguments: { musicPlan: {{ $musicPlan->id }} } })"
                            variant="outline"
                            icon="cog-6-tooth">
                            Beállítások
                        </flux:button>
                        
                        <flux:button
                            wire:click="delete"
                            variant="outline"
                            color="red"
                            icon="trash">
                            Törlés
                        </flux:button>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3">
                        <flux:button
                            wire:click="$dispatch('close')"
                            variant="outline">
                            Mégse
                        </flux:button>
                        
                        <flux:button
                            wire:click="$dispatch('saveMusicPlan', { musicPlan: {{ $musicPlan->id }} })"
                            variant="solid"
                            icon="check">
                            Mentés
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- All slots modal -->
    @if ($showAllSlotsModal)
    <flux:modal wire:model="showAllSlotsModal" size="xl">
        <flux:modal.header>
            <flux:heading size="lg">Összes elem</flux:heading>
        </flux:modal.header>
        
        <flux:modal.body>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:field class="flex-1">
                        <flux:checkbox
                            wire:model.live="filterExcludeExisting"
                            label="Csak még nem hozzáadott elemek" />
                    </flux:field>
                    
                    <flux:text class="text-neutral-600 dark:text-neutral-400">
                        {{ count($allSlots) }} elem
                    </flux:text>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-96 overflow-y-auto">
                    @foreach ($allSlots as $slot)
                    <div class="flex items-center justify-between p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors">
                        <flux:text class="font-medium">{{ $slot->name }}</flux:text>
                        <flux:button
                            wire:click="addSlotDirectly({{ $slot->id }})"
                            variant="ghost"
                            size="sm"
                            icon="plus">
                        </flux:button>
                    </div>
                    @endforeach
                </div>
            </div>
        </flux:modal.body>
        
        <flux:modal.footer>
            <flux:button
                wire:click="closeAllSlotsModal"
                variant="outline">
                Bezárás
            </flux:button>
        </flux:modal.footer>
    </flux:modal>
    @endif
</div>