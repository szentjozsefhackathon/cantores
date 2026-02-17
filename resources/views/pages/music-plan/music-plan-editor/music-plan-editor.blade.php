<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <div class="flex items-center gap-4 mb-4">
                <x-music-plan-setting-icon :genre="$musicPlan->genre" />
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

                            @forelse($planSlots as $slot)
                            <flux:card class="p-2 flex items-start gap-4 {{ count($slot['assignments']) > 0 ? 'border-4' : '' }}">
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
                                                <livewire:music-card :music="App\Models\Music::find($assignment['music_id'])" />
                        <x-mary-choices placeholder="Címkék" wire:model="flags.{{ $assignment['id'] }}" clearable :options="[
                            ['id' => 'important', 'name' => __('Important'), 'icon' => 'o-star'],
                        ]">
                            </x-mary-choices>

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