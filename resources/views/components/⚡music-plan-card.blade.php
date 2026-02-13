<?php

use App\Models\MusicPlan;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    public function mount(MusicPlan $musicPlan): void
    {
        $this->musicPlan = $musicPlan;
    }
};
?>

<flux:card class="music-plan-card p-0 overflow-hidden hover:shadow-lg transition-shadow duration-300 border border-neutral-200 dark:border-neutral-800">
    <a href="{{ route('music-plan-editor', ['musicPlan' => $musicPlan->id]) }}" class="block">
        <div class="p-5 space-y-4">
            <!-- Header with icon and title -->
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <x-music-plan-setting-icon :setting="$musicPlan->setting" />
                    <div class="flex-1">
                        <flux:heading size="lg" class="mb-1">
                            {{ $musicPlan->celebration_name ?? '–' }}
                        </flux:heading>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                            @if($musicPlan->actual_date)
                                <span>{{ \Illuminate\Support\Carbon::parse($musicPlan->actual_date)->translatedFormat('Y. F j.') }}</span>
                                <span>•</span>
                            @endif
                            <span>{{ \App\MusicPlanSetting::tryFrom($musicPlan->setting)?->label() ?? $musicPlan->setting }}</span>
                            <span>•</span>
                            <span class="flex items-center gap-1">
                                <flux:icon name="{{ $musicPlan->is_published ? 'eye' : 'eye-slash' }}" class="h-3 w-3" variant="mini" />
                                {{ $musicPlan->is_published ? 'Közzétéve' : 'Privát' }}
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
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Liturgikus év</flux:heading>
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
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Időszak</flux:heading>
                    <flux:text class="text-base font-semibold">{{ $firstCelebration->season_text }}</flux:text>
                </div>
                @endif

                @if($firstCelebration && ($firstCelebration->week || $musicPlan->day_name))
                <div>
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Hét és nap</flux:heading>
                    <div class="flex flex-wrap gap-2">
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
                            {{ $musicPlan->slots()->count() }} elem
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon name="calendar-days" class="h-4 w-4 text-neutral-500" variant="mini" />
                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                            Létrehozva: {{ $musicPlan->created_at->translatedFormat('Y. m. d. H:i') }}
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:icon name="external-link" class="h-3 w-3" variant="mini" />
                    @if($musicPlan->actual_date)
                        <flux:link 
                            href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ \Illuminate\Support\Carbon::parse($musicPlan->actual_date)->format('Y-m-d') }}" 
                            target="_blank" 
                            class="text-xs"
                            onclick="event.stopPropagation()">
                            Igenaptár
                        </flux:link>
                    @else
                        <flux:text class="text-xs text-neutral-500">Igenaptár (nincs dátum)</flux:text>
                    @endif
                </div>
            </div>
        </div>
    </a>
</flux:card>