<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5" wire:loading.class="opacity-50" wire:target="isPublished">
            <div class="flex items-center gap-4 mb-4">
                <livewire:music-plan-setting-icon :genreId="$genreId" :wire:key="'setting-icon-'.$genreId" />
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
                <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Ünnep neve</flux:heading>
                        @if($musicPlan->hasCustomCelebrations() && $isEditingCelebration)
                        <flux:field>
                            <flux:input
                                wire:model.live="celebrationName"
                                placeholder="Ünnep neve" />
                        </flux:field>
                        @else
                        <div class="flex items-center gap-2">
                            <flux:text class="text-base font-semibold">{{ $musicPlan->celebration_name ?? '–' }}</flux:text>
                            @if($musicPlan->hasCustomCelebrations())
                            <flux:button
                                wire:click="toggleCelebrationEditing"
                                icon="pencil"
                                variant="outline"
                                size="xs"
                                title="Szerkesztés" />
                            @endif
                        </div>
                        @endif
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Dátum</flux:heading>
                        @if($musicPlan->hasCustomCelebrations() && $isEditingCelebration)
                        <flux:field>
                            <flux:input
                                type="date"
                                wire:model.live="celebrationDate" />
                        </flux:field>
                        @else
                        <flux:text class="text-base font-semibold">
                            @if($musicPlan->actual_date)
                            {{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}
                            @else
                            –
                            @endif
                        </flux:text>
                        @endif
                    </div>
                    <livewire:music-plan-editor.genre-select :music-plan="$musicPlan" wire:model.live="genreId"/>
                    @if(!$musicPlan->hasCustomCelebrations())
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
                    @endif
                    @if($musicPlan->hasCustomCelebrations() && $isEditingCelebration)
                    <div class="flex items-end">
                        <flux:button
                            wire:click="saveCelebration"
                            icon="check"
                            variant="primary"
                            size="sm">
                            Mentés
                        </flux:button>
                        <flux:button
                            wire:click="toggleCelebrationEditing"
                            icon="x-mark"
                            variant="outline"
                            size="sm"
                            class="ml-2">
                            Mégse
                        </flux:button>
                    </div>
                    @endif
                </div>

                <!-- Celebration assignment switching -->
                <div class="pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Ünnep hozzárendelés módosítása</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @if($musicPlan->hasCustomCelebrations())
                        <flux:button
                            wire:click="switchToLiturgicalCelebration"
                            icon="calendar"
                            variant="outline"
                            size="sm">
                            Liturgikus ünnepre váltás
                        </flux:button>
                        @else
                        <flux:button
                            wire:click="switchToCustomCelebration"
                            icon="pencil"
                            variant="outline"
                            size="sm">
                            Egyedi ünnepre váltás
                        </flux:button>
                        <flux:button
                            wire:click="switchToLiturgicalCelebration"
                            icon="calendar"
                            variant="outline"
                            size="sm">
                            Liturgikus ünnep cseréje
                        </flux:button>
                        @endif
                    </div>
                </div>

                <!-- Status -->
                <div class="flex items-center justify-between pt-4 border-t border-neutral-200 dark:border-neutral-800" >
                    <div class="flex items-center gap-3">
                        <flux:icon name="{{ $isPublished ? 'eye' : 'eye-slash' }}" class="h-5 w-5 {{ $isPublished ? 'text-green-500' : 'text-neutral-500' }}" variant="mini" />
                        <flux:field variant="inline" class="mb-0" >
                            <flux:label>Közzététel</flux:label>
                            <flux:switch wire:model.live="isPublished" 
                            wire:loading.attr="disabled"
                            
                            wire:target="isPublished"

                            />
                        </flux:field>
                        <div class="flex items-center">
                            @if($musicPlan->actual_date)
                            <flux:icon name="external-link" class="mr-1" />
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
                                    <div class="flex flex-col gap-2">
                                        <flux:button
                                            wire:click="openCreateSlotModal"
                                            icon="plus"
                                            variant="outline"
                                            class="whitespace-nowrap">
                                            Új egyedi elem
                                        </flux:button>
                                        <flux:button
                                            wire:click="showAllSlots"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            icon="list-bullet"
                                            variant="outline"
                                            class="whitespace-nowrap">
                                            Összes elem
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- All Slots Modal -->
                            @if($showAllSlotsModal)
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
                                                    wire:target="addSlotDirectly({{ $slot['id'] }})" />
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
                            @endif

                            @if($showCreateSlotModal)
                            <!-- Create Custom Slot Modal -->
                            <flux:modal wire:model="showCreateSlotModal" size="md">
                                <flux:heading size="lg">Új egyedi elem</flux:heading>

                                <div class="mt-6 space-y-4">
                                    <flux:field>
                                        <flux:label>Elem neve *</flux:label>
                                        <flux:input
                                            wire:model.live="newSlotName"
                                            placeholder="Közbenjárás, köszöntés stb."
                                            autofocus />
                                            <flux:error name="newSlotName" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Leírás (opcionális)</flux:label>
                                        <flux:textarea
                                            wire:model.live="newSlotDescription"
                                            placeholder="Rövid leírás az elemről..."
                                            rows="3" />
                                            <flux:error name="newSlotDescription" />
                                    </flux:field>
                                </div>

                                <div class="mt-8 flex justify-end gap-3">
                                    <flux:button
                                        wire:click="closeCreateSlotModal"
                                        variant="outline">
                                        Mégse
                                    </flux:button>
                                    <flux:button
                                        wire:click="createCustomSlot"
                                        variant="primary"
                                        icon="plus">
                                        <span wire:loading.remove wire:target="createCustomSlot">Létrehozás</span>
                                        <span wire:loading wire:target="createCustomSlot">Feldolgozás...</span>
                                    </flux:button>
                                </div>
                            </flux:modal>
                            @endif

                            @if($showMusicSearchModal)
                            <!-- Music Search Modal -->
                            <flux:modal wire:model="showMusicSearchModal" class="max-w-4xl">
                                <livewire:music-search selectable="true" />
                                <div class="mt-6 flex justify-end">
                                    <flux:button
                                        wire:click="closeMusicSearchModal"
                                        variant="outline">
                                        Bezárás
                                    </flux:button>
                                </div>
                            </flux:modal>
                            @endif

                            @forelse($planSlots as $slot)
                            <flux:card wire:key="slot-{{ $slot['pivot_id'] }}" class="p-2 flex items-start gap-4 {{ count($slot['assignments']) > 0 ? 'border-4' : '' }}">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-semibold">
                                    {{ $slot['sequence'] }}
                                </div>
                                <div class="flex-1 space-y-1">
                                    @if($editingSlotId === $slot['id'])
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
                                    <flux:heading size="sm">{{ $slot['name'] }}</flux:heading>
                                    @if($slot['description'])
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($slot['description'], 120) }}</flux:text>
                                    @endif
                                    @endif

                                    <!-- Assigned music -->
                                    @if(!empty($slot['assignments']))
                                    <div class="mt-3 space-y-2">
                                        @foreach($slot['assignments'] as $assignment)
                                        <div wire:key="assignment-{{ $assignment['id'] }}" class="flex items-center justify-between bg-neutral-50 dark:bg-neutral-800 rounded-lg px-3 py-2">
                                            <div class="flex gap-3">
                                                @if(count($slot['assignments']) > 1)
                                                <div class="flex flex-col gap-1">
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
                                                    <flux:button
                                                        wire:click="removeAssignment({{ $assignment['id'] }})"
                                                        wire:confirm="Biztosan eltávolítod ezt a zenét az elemből?"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                                        icon="trash"
                                                        variant="danger"
                                                        size="xs" />

                                                </div>

                                                @endif
                                                <div class="flex flex-col gap-2">
                                                    <livewire:music-card :music="App\Models\Music::find($assignment['music_id'])" wire:loading />
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
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-col gap-1">
                                        @if($editingSlotId === $slot['id'])
                                        <!-- Save/Cancel buttons when editing -->
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
                                        <!-- Normal buttons when not editing -->
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
                                        <div class="border-b border-neutral-300 dark:border-neutral-700 w-6"></div>
                                        <flux:button
                                            wire:click="openMusicSearchModal({{ $slot['pivot_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            icon="plus"
                                            variant="outline"
                                            size="xs"
                                            title="Zene hozzáadása" />
                                        @if($slot['is_custom'])
                                        <flux:button
                                            wire:click="startEditingSlot({{ $slot['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            icon="pencil"
                                            variant="outline"
                                            size="xs"
                                            title="Szerkesztés" />
                                        @endif
                                        <flux:button
                                            wire:click="deleteSlot({{ $slot['pivot_id'] }})"
                                            wire:confirm="Biztosan eltávolítod ezt az elemet az énekrendből?"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-50 cursor-not-allowed"
                                            icon="trash"
                                            variant="danger"
                                            size="xs" />
                                        @endif
                                    </div>
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

                            <livewire:music-plan-editor.music-plan-template :templates="$availableTemplates" :musicPlan="$musicPlan" />
                        </div>
                    </div>
                </div>

                                <!-- Private notes -->
                <div class="pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Privát megjegyzések (csak neked látható)</flux:heading>
                    <flux:field>
                        <flux:textarea
                            wire:model.live="privateNotes"
                            placeholder="Írj ide privát megjegyzéseket az énekrenddel kapcsolatban (pl. emlékeztetők, gondolatok)..."
                            rows="4"
                            class="w-full" />
                    </flux:field>
                    <div class="flex justify-end mt-2">
                        <flux:button
                            wire:click="savePrivateNotes"
                            wire:loading.attr="disabled"
                            icon="check"
                            variant="primary"
                            size="sm">
                            Mentés
                        </flux:button>
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
                
                <!-- Celebration selector modal -->
                @if ($showCelebrationSelector)                 
                <flux:modal wire:model.self="showCelebrationSelector" title="Liturgikus ünnep kiválasztása" class="md:w-2xl">
                    <div class="flex flex-col gap-4">
                    <livewire:liturgical-info selectable />

                    <div class="flex">
                        <flux:spacer />
                        <flux:button
                            wire:click="cancelCelebrationSelection">
                            Mégse
                        </flux:button>
                    </div>
                    </div>
                </flux:modal>
                @endif
    </div>
    </flux:card>
</div>
</div>