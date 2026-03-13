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
                    <livewire:music-plan-setting-icon :genreId="$musicPlan->genre_id" />
                    <div class="flex-1">
                        <flux:heading size="lg" class="mb-1">
                            {{ $musicPlan->celebration_name ?? '–' }}
                        </flux:heading>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                            @if($musicPlan->actual_date)
                                <span>{{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}</span>
                            @endif
                            <span class="flex items-center gap-1">
                                <flux:icon name="{{ !$musicPlan->is_private ? 'globe' : 'globe-lock' }}" class="h-3 w-3" variant="mini" />
                                <span class="hidden md:inline">
                                    {{ !$musicPlan->is_private ? 'Publikus' : 'Privát' }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
                <flux:icon name="chevron-right" class="h-5 w-5 text-neutral-400 group-hover:text-blue-600" variant="mini" />
            </div>

            <!-- Liturgical details -->
            @php
                $firstCelebration = $musicPlan->celebration;
            @endphp
            <div class="flex flex-row gap-3 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                @if($firstCelebration && ($firstCelebration->year_letter || $firstCelebration->year_parity))
                    <flux:text class="text-base font-semibold" size="sm">
                        {{ $firstCelebration->year_letter ?? '–' }} 
                        @if($firstCelebration->year_parity)
                        ({{ $firstCelebration->year_parity }})
                        @endif év
                    </flux:text>
                @endif

                @if($firstCelebration && $firstCelebration->season_text)
                    <flux:text class="text-base font-semibold" size="sm">{{ $firstCelebration->season_text }}</flux:text>
                @endif

                @if($firstCelebration && ($firstCelebration->week || $musicPlan->day_name))
                        @if($firstCelebration->week)
                        <flux:badge color="green" size="sm">{{ $firstCelebration->week }}. hét</flux:badge>
                        @endif
                        @if($musicPlan->day_name)
                        <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                        @endif
                @endif
            </div>

            <!-- Slot count and creation date -->
            <div class="flex items-center justify-between pt-3 border-t border-neutral-100 dark:border-neutral-800">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <flux:text class="text-sm">
                            {{ $musicPlan->musicAssignments()->count() }} elem
                        </flux:text>
                    </div>
                    <x-user-badge :user="$musicPlan->user" />
                </div>
            </div>
        </div>
    </a>
</flux:card>