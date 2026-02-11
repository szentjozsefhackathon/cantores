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

    public function mount(MusicPlan $musicPlan): void
    {
        $this->authorize('view', $musicPlan);
        $this->musicPlan = $musicPlan;
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
                return [
                    'id' => $slot->id,
                    'pivot_id' => $slot->pivot->id,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
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

        $this->redirectRoute('dashboard');
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
        $this->dispatch('slots-updated');
        $this->dispatch('notify', message: 'Elem hozzáadva.', type: 'success');
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

        $this->dispatch('slots-updated');
        $this->dispatch('notify', message: $addedCount . ' elem hozzáadva a sablonból.', type: 'success');
    }

    public function moveSlotUp(int $pivotId): void
    {
        $this->reorderSlot($pivotId, 'up');
    }

    public function moveSlotDown(int $pivotId): void
    {
        $this->reorderSlot($pivotId, 'down');
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
};
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <div class="flex items-center gap-4 mb-4">
                <flux:icon name="musical-note" class="h-7 w-7 text-blue-600" variant="outline" />
                <flux:heading size="xl">Énekrend szerkesztése</flux:heading>
            </div>

            <div class="space-y-4">
                <!-- Combined info grid -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Ünnep neve</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->celebration_name }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Dátum</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Liturgikus év</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->year_letter ?? '–' }} {{ $musicPlan->year_parity ? '(' . $musicPlan->year_parity . ')' : '' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Hangszerek</flux:heading>
                        <flux:text class="text-base font-semibold">{{ \App\MusicPlanSetting::tryFrom($musicPlan->setting)?->label() ?? $musicPlan->setting }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Időszak, hét, nap</flux:heading>
                        <div class="flex flex-row gap-2">
                            <flux:badge color="blue" size="sm">{{ $musicPlan->season_text }}</flux:badge>
                            <flux:badge color="green" size="sm">{{ $musicPlan->week }}.hét</flux:badge>
                            <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                        </div>
                    </div>
                </div>


                <!-- Status -->
                <div class="flex items-center justify-between pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="flex items-center gap-3">
                        <flux:icon name="{{ $musicPlan->is_published ? 'eye' : 'eye-slash' }}" class="h-5 w-5 text-neutral-500" variant="mini" />
                        <flux:text class="font-medium">{{ $musicPlan->is_published ? 'Közzétéve' : 'Privát' }}</flux:text>
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
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <flux:button
                                            wire:click="moveSlotUp({{ $slot['pivot_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            :disabled="$loop->first"
                                            icon="chevron-up"
                                            variant="outline"
                                            size="xs"/>
                                        <flux:button
                                            wire:click="moveSlotDown({{ $slot['pivot_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            :disabled="$loop->last"
                                            icon="chevron-down"
                                            variant="outline"
                                            size="xs"/>
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
                                                            variant="mini"
                                                        />
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
                                                            size="sm"
                                                        >
                                                            <span wire:loading.remove>Összes</span>
                                                            <span wire:loading>Feldolgozás...</span>
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
                                                                            class="h-4 w-4 {{ $slot['is_included_by_default'] ? 'text-amber-500' : 'text-neutral-400' }}"
                                                                        />
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
                                                                    class="ml-4"
                                                                >
                                                                    <span wire:loading.remove>Hozzáadás</span>
                                                                    <span wire:loading>...</span>
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
                        wire:confirm="Biztosan törölni szeretnéd ezt az énekrendet? A művelet nem visszavonható."
                    >
                        Énekrend törlése
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</div>