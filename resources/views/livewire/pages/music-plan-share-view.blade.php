<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <flux:card class="p-5">
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <x-genre-icon :genre-id="$musicPlan->genre_id" />
                <flux:heading size="xl">Énekrend</flux:heading>
                <x-user-badge :user="$musicPlan->user" />
                <flux:badge color="amber" icon="lock-closed">Titkos megosztás</flux:badge>

                @if($musicPlan->actual_date)
                <div class="flex items-center gap-1">
                    <flux:icon name="external-link" class="h-3 w-3" variant="mini" />
                    <flux:link href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ $musicPlan->actual_date->format('Y-m-d') }}" target="_blank" class="text-xs">
                        Igenaptár
                    </flux:link>
                </div>
                @endif
            </div>

            <div class="space-y-4">
                <!-- Combined info grid -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1 inline">Ünnep:
                            <flux:text class="text-base font-semibold inline">{{ $musicPlan->celebration_name ?? '–' }}</flux:text>
                        </flux:heading>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1 inline">Dátum:
                            <flux:text class="text-base font-semibold inline">
                                @if($musicPlan->actual_date)
                                    {{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}
                                @else
                                    –
                                @endif
                            </flux:text>
                        </flux:heading>
                    </div>
                    @php
                        $firstCelebration = $musicPlan->celebration;
                    @endphp
                    <div>
                        <div class="flex flex-row gap-2">
                            <flux:badge color="zinc">{{ $firstCelebration?->year_letter . ' év' ?? '–' }} {{ $firstCelebration?->year_parity ? '(' . $firstCelebration->year_parity . ')' : '' }}</flux:badge>
                            <flux:badge color="blue" size="sm">{{ $firstCelebration?->season_text ?? '–' }}</flux:badge>
                            <flux:badge color="green" size="sm">{{ $firstCelebration?->week ?? '–' }}.hét</flux:badge>
                            <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                        </div>
                    </div>
                </div>

                @if($musicPlan->private_notes)
                <!-- Private notes always shown on secret link page -->
                <div class="pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Privát megjegyzések</flux:heading>
                    <flux:card class="p-4 bg-neutral-50 dark:bg-neutral-900/50">
                        <flux:text class="whitespace-pre-wrap">{{ $musicPlan->private_notes }}</flux:text>
                    </flux:card>
                </div>
                @endif

                <!-- Plan elements -->
                <div class="pt-6 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-4 lg:col-span-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <flux:heading size="lg">Énekrend elemei</flux:heading>
                                <flux:badge color="zinc" size="sm">{{ count($planSlots) }} elem</flux:badge>
                            </div>

                            @forelse($planSlots as $slot)
                            <flux:card class="p-2 space-y-2 min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 text-xs font-semibold shrink-0">
                                        {{ $slot['sequence'] }}
                                    </div>
                                    <flux:heading size="sm">{{ $slot['name'] }}</flux:heading>
                                </div>
                                @if($slot['description'])
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $slot['description'] }}</flux:text>
                                @endif

                                @if(!empty($slot['assignments']))
                                <div class="space-y-3">
                                    @foreach($slot['assignments'] as $assignment)
                                        @if(!empty($assignment['music']))
                                            @if(!empty($assignment['scope_label']))
                                                <div class="mb-1">
                                                    <flux:badge color="zinc" size="sm">{{ $assignment['scope_label'] }}</flux:badge>
                                                </div>
                                            @endif
                                            <livewire:music-card
                                                :key="'music-card-'.$assignment['id']"
                                                :music="$assignment['music']"
                                                :private-share="true"
                                                :share-scores="$assignment['scores']"
                                            />
                                        @else
                                        <flux:callout variant="secondary" icon="information-circle">
                                            A zenei bejegyzés már nem érhető el.
                                        </flux:callout>
                                        @endif

                                        @if(!empty($assignment['notes']))
                                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                            {{ $assignment['notes'] }}
                                        </flux:text>
                                        @endif
                                    @endforeach
                                </div>
                                @else
                                <flux:callout variant="secondary" icon="musical-note">
                                    Ehhez az elemhez nincs zene hozzárendelve.
                                </flux:callout>
                                @endif
                            </flux:card>
                            @empty
                            <flux:callout variant="secondary" icon="musical-note">
                                Ehhez az énekrendhez még nem adtál elemeket.
                            </flux:callout>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4">
                    <flux:button variant="outline" color="zinc" icon="arrow-left" href="{{ route('home') }}">
                        Vissza a kezdőlapra
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</div>
