<?php

use App\Models\MusicPlan;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;
    public bool $readonly;

    public function mount(MusicPlan $musicPlan, bool $readonly = false): void
    {
        $this->musicPlan = $musicPlan;
        $this->readonly = $readonly;
    }
};
?>

<flux:card class="music-plan-card p-0 overflow-hidden hover:shadow-lg transition-shadow duration-300 border border-neutral-200 dark:border-neutral-800">
    @if (!$this->readonly)
    <a href="{{ route('music-plan-editor', ['musicPlan' => $musicPlan->id]) }}" class="block">
    @else
    <a href="{{ route('music-plan-view', ['musicPlan' => $musicPlan->id]) }}" class="block">
    @endif
        <div class="p-5 space-y-4">
            <!-- Header with icon and title -->
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <x-music-plan-setting-icon :genre="$musicPlan->genre" />
                    <div class="flex-1">
                        <flux:heading size="lg" class="mb-1">
                            {{ $musicPlan->celebration_name ?? '–' }}
                        </flux:heading>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                            @if($musicPlan->actual_date)
                                <span>{{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}</span>
                            @endif
                            <span class="flex items-center gap-1">
                                <flux:icon name="{{ !$musicPlan->is_private ? 'eye' : 'eye-slash' }}" class="h-3 w-3" variant="mini" />
                                {{ !$musicPlan->is_private ? 'Közzétéve' : 'Privát' }}
                            </span>
                        </div>
                    </div>
                </div>
                <flux:icon name="chevron-right" class="h-5 w-5 text-neutral-400 group-hover:text-blue-600" variant="mini" />
            </div>

            <!-- Liturgical details -->
            @php
                $firstCelebration = $musicPlan->celebrations->first();
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                @if($firstCelebration && ($firstCelebration->year_letter || $firstCelebration->year_parity))
                <div>
                    <flux:text class="text-base font-semibold">
                        {{ $firstCelebration->year_letter ?? '–' }} 
                        @if($firstCelebration->year_parity)
                        ({{ $firstCelebration->year_parity }})
                        @endif
                    </flux:text>
                </div>
                @endif

                @if($firstCelebration && $firstCelebration->season_text)
                <div>
                    <flux:text class="text-base font-semibold">{{ $firstCelebration->season_text }}</flux:text>
                </div>
                @endif

                @if($firstCelebration && ($firstCelebration->week || $musicPlan->day_name))
                <div>
                    <div class="flex flex-row gap-2">
                        @if($firstCelebration->week)
                        <flux:badge color="green" size="sm">{{ $firstCelebration->week }}. hét</flux:badge>
                        @endif
                        @if($musicPlan->day_name)
                        <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- Slot count and creation date -->
            <div class="flex items-center justify-between pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <flux:icon name="musical-note" class="h-4 w-4 text-blue-600 dark:text-blue-400" variant="mini" />
                        <flux:text class="text-sm">
                            {{ $musicPlan->musicAssignments()->count() }} elem
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon name="calendar-days" class="h-4 w-4 text-neutral-500" variant="mini" />
                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                            {{ $musicPlan->created_at->translatedFormat('Y. m. d. H:i') }}
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($musicPlan->actual_date)
                    <flux:icon name="external-link" class="h-3 w-3" variant="mini" />                    
                        <flux:link 
                            href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ $musicPlan->actual_date->format('Y-m-d') }}"
                            target="_blank" 
                            class="text-xs"
                            onclick="event.stopPropagation()">
                            Igenaptár
                        </flux:link>
                    @endif
                </div>
            </div>
        </div>
    </a>
</flux:card>