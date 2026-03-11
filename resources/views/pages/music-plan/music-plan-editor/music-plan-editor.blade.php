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
                                wire:model="celebrationName"
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
                                wire:model="celebrationDate" />
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
                        $firstCelebration = $musicPlan->celebration;
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
                        <flux:icon name="{{ $isPublished ? 'globe' : 'globe-lock' }}" class="h-5 w-5 {{ $isPublished ? 'text-green-500' : 'text-neutral-500' }}" variant="mini" />
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
                                <flux:badge color="zinc" size="sm">{{ $this->planSlots->count() }} elem</flux:badge>
                            </div>

                            <!-- Slot Search Component -->
                            <livewire:music-plan-editor.slot-search defer :music-plan="$musicPlan" />

                            @forelse($this->planSlots as $slotPlan)
                            <livewire:music-plan-editor.slot-plan
                                :slot-plan="$slotPlan"
                                :is-first="$loop->first"
                                :is-last="$loop->last"
                                :total-slots="$this->planSlots->count()"
                                wire:key="slot-{{ $slotPlan->id }}-{{ $slotPlan->sequence }}" />
                            @empty
                            <flux:callout variant="secondary" icon="musical-note">
                                Ehhez az énekrendhez még nem adtál elemeket.
                            </flux:callout>
                            @endforelse
                        </div>

                        <div class="space-y-4">
                            <!-- Tabs for template and suggestions -->
                            <x-mary-tabs class="mb-8" wire:model="activeTemplateTab" wire:key="template-tabs">
                                <x-mary-tab name="template" label="Énekrend sablon">
                                    <div class="space-y-4">
                                        <livewire:music-plan-editor.music-plan-template lazy :templates="$availableTemplates" :musicPlan="$musicPlan" wire:key="template-component" />
                                    </div>
                                </x-mary-tab>

                                <x-mary-tab name="suggestions" label="Énekek hozzáadása énekrendből">
                                    <div class="space-y-4">
                                        <livewire:suggestions-content lazy :criteria="[
                                            'name' => $musicPlan->celebration_name,
                                            'season' => $musicPlan->celebration?->season,
                                            'week' => $musicPlan->celebration?->week,
                                            'day' => $musicPlan->celebration?->day,
                                            'readings_code' => $musicPlan->celebration?->readings_code,
                                            'year_letter' => $musicPlan->celebration?->year_letter,
                                            'year_parity' => $musicPlan->celebration?->year_parity,
                                        ]" :musicPlanId="$musicPlan->id" wire:key="suggestions-component" />
                                    </div>
                                </x-mary-tab>
                            </x-mary-tabs>
                        </div>
                    </div>
                </div>

                                <!-- Private notes -->
                <div class="pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Privát megjegyzések (csak neked látható)</flux:heading>
                    <flux:field>
                        <flux:textarea
                            wire:model="privateNotes"
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
                    <livewire:music-plan-share-modal :music-plan="$musicPlan" />
                    <form method="POST" action="{{ route('music-plans.copy', $musicPlan) }}" class="inline">
                        @csrf
                        <flux:button type="submit" variant="outline" color="blue" icon="clipboard-copy"
                            wire:confirm="{{ __('Are you sure you want to copy this music plan?') }}"

                        >
                            Másolat készítése
                        </flux:button>
                    </form>
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