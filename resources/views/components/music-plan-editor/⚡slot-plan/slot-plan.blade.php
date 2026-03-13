<div>
<flux:card class="p-2 {{ count($assignments) > 0 ? 'border-4' : '' }}">
    <div class="grid grid-cols-1 gap-4 grid-cols-[1fr_auto]">
        <!-- Left column: sequence, name/description, and assignments -->
        <div class="space-y-4">
            <!-- First row: sequence and name/description -->
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-semibold flex-shrink-0">
                    {{ $slotPlan->sequence }}
                </div>
                <div class="flex-1 space-y-1">
                    @if($isEditingSlot)
                    <div class="space-y-3">
                        <flux:field>
                            <flux:input
                                wire:model.live="editingSlotName"
                                placeholder="Elem neve"
                                autofocus />
                            <flux:error name="editingSlotName" />
                        </flux:field>
                        <flux:field>
                            <flux:textarea
                                wire:model.live="editingSlotDescription"
                                placeholder="Leírás (opcionális)"
                                rows="2" />
                            <flux:error name="editingSlotDescription" />
                        </flux:field>
                    </div>
                    @else
                    <flux:heading size="sm">{{ $slotPlan->musicPlanSlot->name }}</flux:heading>
                    @if($slotPlan->musicPlanSlot->description)
                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($slotPlan->musicPlanSlot->description, 120) }}</flux:text>
                    @endif
                    @endif
                </div>
            </div>

            <!-- Second row: Assigned music -->
            @if(!empty($assignments))
            <div class="space-y-2">
            @foreach($assignments as $assignment)
            <div wire:key="assignment-{{ $assignment['id'] }}" class="flex items-center justify-between bg-neutral-50 dark:bg-neutral-800 rounded-lg px-3 py-2">
                <div class="flex gap-3">
                    <!-- Button bar -->
                    <div class="flex flex-col gap-1">
                        @if(count($assignments) > 1)
                        <flux:badge>{{ $assignment['music_sequence'] }}</flux:badge>
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
                        @endif

                        <flux:button
                            wire:click="removeAssignment({{ $assignment['id'] }})"
                            wire:confirm="Biztosan eltávolítod ezt a zenét az elemből?"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            icon="trash"
                            variant="danger"
                            size="xs" />

                        @if($totalSlots > 1)
                        <flux:button
                            wire:click="openMoveAssignmentModal({{ $assignment['id'] }})"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            icon="arrow-right"
                            variant="outline"
                            size="xs"
                            title="Áthelyezés másik elembe" />
                        @endif
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-music-card-static :assignment="$assignment" />
                        <div class="text-sm">
                        <x-mary-choices
                            placeholder="Címkék"
                            wire:model.live="flags.{{ $assignment['id'] }}"
                            clearable
                            :options="$this->flagOptions">
                            @scope('item', $option)
                            <x-mary-list-item
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                                :item="$option" class="h-8">
                                <x-slot:avatar>
                                    <x-mary-icon :name="$option['icon']" :class="'text-'.$option['color'].'-500'" />
                                </x-slot:avatar>
                            </x-mary-list-item>
                            @endscope
                            @scope('selection', $option)
                            <x-mary-icon :name="$option['icon']" class="text-{{ $option['color'] }}-500 w-4 h-4" />
                            <flux:text size="sm" class="inline {{ 'text-'.$option['color'].'-500'}}">{{ $option['name'] }}</flux:text>
                            @endscope
                        </x-mary-choices>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <!-- Saved scope badges -->
                            @foreach(($this->assignmentScopes[$assignment['id']] ?? []) as $index => $scope)
                            @if(!empty($scope['type']) && !empty($scope['number']))
                            @php
                                $scopeTypeLabel = collect($this->scopeTypeOptions)->firstWhere('value', $scope['type'])['label'] ?? $scope['type'];
                            @endphp
                            <div class="inline-flex items-center gap-1 bg-neutral-100 dark:bg-neutral-800 rounded-full px-2 py-1 text-xs font-medium">
                                <span>{{ $scopeTypeLabel }} {{ $scope['number'] }}</span>
                                <button
                                    type="button"
                                    wire:click="removeScope({{ $assignment['id'] }}, {{ $index }})"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    class="h-4 w-4 flex items-center justify-center rounded-full hover:bg-neutral-200 dark:hover:bg-neutral-700 text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-300">
                                    <flux:icon name="x-mark" class="h-3 w-3" />
                                </button>
                            </div>
                            @endif
                            @endforeach

                            <!-- New scope form -->
                            @foreach(($this->assignmentScopes[$assignment['id']] ?? []) as $index => $scope)
                            @if(empty($scope['type']) || empty($scope['number']))
                            <div class="flex gap-2 items-center">
                                <div class="flex-1">
                                    <flux:field variant="inline" class="mb-0">
                                        <flux:select
                                            wire:model="assignmentScopes.{{ $assignment['id'] }}.{{ $index }}.type"
                                            placeholder="Válassz..."
                                            size="sm"
                                            class="w-full">
                                            <flux:select.option value="">–</flux:select.option>
                                            @foreach($this->scopeTypeOptions as $option)
                                            <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </flux:field>
                                </div>
                                <div class="flex-1">
                                    <flux:field variant="inline" class="mb-0">
                                        <flux:input
                                            type="number"
                                            min="1"
                                            wire:model="assignmentScopes.{{ $assignment['id'] }}.{{ $index }}.number"
                                            placeholder="Szám, pl. 1"
                                            class="w-full"
                                            size="sm" />
                                    </flux:field>
                                </div>
                                <div class="pt-5 flex gap-1">
                                    <flux:button
                                        wire:click="saveScope({{ $assignment['id'] }}, {{ $index }})"
                                        icon="check"
                                        variant="primary"
                                        size="xs" />
                                    <flux:button
                                        wire:click="removeScope({{ $assignment['id'] }}, {{ $index }})"
                                        icon="x-mark"
                                        variant="danger"
                                        size="xs" />
                                </div>
                            </div>
                            @endif
                            @endforeach

                            <div class="pt-1">
                                <flux:button
                                    wire:click="addScope({{ $assignment['id'] }})"
                                    icon="plus"
                                    variant="outline"
                                    size="xs">
                                    További részlet
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            </div>
            @endif
        </div>

        <!-- Right column: slot-level action buttons -->
        <div class="flex items-start gap-2">
            <div class="flex flex-col gap-1">
            @if($isEditingSlot)
            <flux:button
                wire:click="saveEditedSlot"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                icon="check"
                variant="primary"
                size="xs"
                title="Mentés" />
            <flux:button
                wire:click="cancelEditingSlot"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                icon="x-mark"
                variant="outline"
                size="xs"
                title="Mégse" />
            @else
            <flux:button
                wire:click="moveSlotUp"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                :disabled="$isFirst"
                icon="chevron-up"
                variant="outline"
                size="xs" />
            <flux:button
                wire:click="moveSlotDown"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                :disabled="$isLast"
                icon="chevron-down"
                variant="outline"
                size="xs" />
            <div class="border-b border-neutral-300 dark:border-neutral-700 w-6"></div>
            <flux:button
                x-on:click="$wire.dispatch('open-music-search', { slotPlanId: {{ $slotPlan->id }} })"
                icon="plus"
                variant="outline"
                size="xs"
                title="Zene hozzáadása" />
            @if($slotPlan->musicPlanSlot->is_custom)
            <flux:button
                wire:click="startEditingSlot"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                icon="pencil"
                variant="outline"
                size="xs"
                title="Szerkesztés" />
            @endif
            <flux:button
                wire:click="deleteSlot"
                wire:confirm="Biztosan eltávolítod ezt az elemet az énekrendből?"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                icon="trash"
                variant="danger"
                size="xs" />
            @endif
            </div>
        </div>
    </div>
</flux:card>

<flux:modal wire:model="showMoveAssignmentModal" size="md">
    <flux:heading size="lg">Zene áthelyezése másik elembe</flux:heading>

    <div class="mt-6 space-y-4">
        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
            Válassz ki egy elemet, ahova áthelyezed ezt a zenét:
        </flux:text>

        @if($assignmentToMove)
        <div class="space-y-2 max-h-96 overflow-y-auto border border-neutral-200 dark:border-neutral-700 rounded-lg p-3">
            @forelse($this->otherSlots as $otherSlot)
            <button
                type="button"
                wire:click="moveAssignmentToSlot({{ $assignmentToMove }}, {{ $otherSlot->id }})"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50 cursor-not-allowed"
                class="w-full text-left p-3 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded-lg border border-neutral-200 dark:border-neutral-700 transition">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-semibold text-sm">
                        {{ $otherSlot->sequence }}
                    </div>
                    <div class="flex-1">
                        <div class="font-medium">{{ $otherSlot->musicPlanSlot->name }}</div>
                        @if($otherSlot->musicPlanSlot->description)
                        <div class="text-xs text-neutral-600 dark:text-neutral-400">{{ Str::limit($otherSlot->musicPlanSlot->description, 80) }}</div>
                        @endif
                    </div>
                    <flux:icon name="arrow-right" class="h-4 w-4 text-neutral-400" />
                </div>
            </button>
            @empty
            <div class="p-4 text-center">
                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                    Nincs más elem az énekrendben.
                </flux:text>
            </div>
            @endforelse
        </div>
        @endif
    </div>

    <div class="mt-6 flex justify-end">
        <flux:button
            wire:click="closeMoveAssignmentModal"
            variant="outline">
            Mégse
        </flux:button>
    </div>
</flux:modal>
</div>