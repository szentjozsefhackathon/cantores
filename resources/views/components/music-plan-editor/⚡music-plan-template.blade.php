<?php

use Livewire\Component;

new class extends Component
{
    /** @var array */
    public $templates = [];

    /** @var mixed */
    public $musicPlan;

    /** @var array */
    public $expandedTemplates = [];

    public function mount(array $templates = [], $musicPlan = null)
    {
        $this->templates = $templates;
        $this->musicPlan = $musicPlan;
    }

    public function toggleTemplate($templateId)
    {
        if (in_array($templateId, $this->expandedTemplates)) {
            $this->expandedTemplates = array_diff($this->expandedTemplates, [$templateId]);
        } else {
            $this->expandedTemplates[] = $templateId;
        }
    }

    public function addSlotsFromTemplate($templateId)
    {
        $this->dispatch('add-slots-from-template', templateId: $templateId);
    }

    public function addDefaultSlotsFromTemplate($templateId)
    {
        $this->dispatch('add-default-slots-from-template', templateId: $templateId);
    }

    public function addSlotFromTemplate($templateId, $slotId)
    {
        $this->dispatch('add-slot-from-template', templateId: $templateId, slotId: $slotId);
    }
};
?>

<div x-data="{ expanded: {} }">
    @if(count($templates) > 0)
        <div class="space-y-4">
            @foreach($templates as $template)
                <flux:card class="overflow-hidden" size="sm">
                    <div class="p-1 cursor-pointer hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
                        @click="expanded[{{ $template['id'] }}] = !expanded[{{ $template['id'] }}]">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-2">
                                <flux:icon
                                    name="chevron-right"
                                    x-bind:class="expanded[{{ $template['id'] }}] ? 'rotate-90' : ''"
                                    class="h-4 w-4 transition-transform duration-200"
                                    variant="mini" />
                                <div>
                                    <flux:heading size="sm" class="mb-1">{{ $template['name'] }}</flux:heading>
                                    @if($template['description'])
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($template['description'], 80) }}</flux:text>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
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

                    <div class="border-t border-neutral-200 dark:border-neutral-800 transition-all duration-300"
                        :class="expanded[{{ $template['id'] }}] ? 'block opacity-100' : 'hidden opacity-0'">
                        <div class="p-4 space-y-3">
                            @foreach($template['slots'] as $slot)
                                <div class="flex items-center justify-between gap-3 py-2 px-3 bg-neutral-50 dark:bg-neutral-800 rounded-lg">
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
                                        size="sm">
                                        <span class="hidden md:inline" wire:target="addSlotFromTemplate({{ $template['id'] }}, {{ $slot['id'] }})">Hozzáadás</span>
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @else
        <flux:callout variant="secondary" icon="information-circle">
            Nincsenek elérhető sablonok. Először hozz létre sablonokat az admin felületen.
        </flux:callout>
    @endif
</div>