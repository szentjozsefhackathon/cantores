<?php

use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;
    public array $planSlots = [];
    public bool $isOwner = false;
    public bool $showOpenButton = false;

    public function mount(MusicPlan $musicPlan): void
    {
        // Check authorization using Gate (supports guest users)
        if (!Gate::allows('view', $musicPlan)) {
            abort(403);
        }

        $this->musicPlan = $musicPlan;

        // Check if current user is the owner
        $this->isOwner = Auth::check() && Auth::id() === $this->musicPlan->user_id;

        // Load plan slots
        $this->loadPlanSlots();
    }

    private function loadPlanSlots(): void
    {
        $user = Auth::user();
        $assignmentsByPivot = $this->musicPlan->musicAssignments()
            ->with(['music.collections', 'scopes'])
            ->orderBy('music_plan_slot_plan_id')
            ->orderBy('music_sequence')
            ->get()
            ->groupBy('music_plan_slot_plan_id');

        $this->planSlots = $this->musicPlan->slots()
            ->visibleToUser($user)
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get()
            ->map(function ($slot) use ($assignmentsByPivot) {
                $pivotId = $slot->pivot->id;
                $assignments = $assignmentsByPivot->get($pivotId, collect());

                return [
                    'id' => $slot->id,
                    'pivot_id' => $pivotId,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'assignments' => $assignments->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'music_id' => $assignment->music_id,
                            'music_sequence' => $assignment->music_sequence,
                            'notes' => $assignment->notes,
                            'music' => $assignment->music,
                            'scope_label' => $assignment->scope_label,
                        ];
                    })->all(),
                ];
            })
            ->values()
            ->all();
    }
};
?>

<div {{ $attributes->merge(['class' => 'max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden']) }}>
    <!-- Header with icon and title -->
    <div class="p-3 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <livewire:music-plan-setting-icon :genreId="$musicPlan->genre_id" />
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                        {{ $musicPlan->celebration_name ?? '–' }}
                    </h3>
                    <div class="flex flex-wrap items-center gap-1 text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                        @if($musicPlan->actual_date)
                        <span>{{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}</span>
                        @endif
                        <span class="flex items-center gap-0.5">
                            <flux:icon name="{{ !$musicPlan->is_private ? 'eye' : 'eye-slash' }}" class="h-3 w-3" variant="mini" />
                            {{ !$musicPlan->is_private ? 'Közzétéve' : 'Privát' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="gap-2">
                @if($showOpenButton)
                    @auth
                        <x-user-badge :user="$musicPlan->user" />
                    @endauth
                <div class="flex justify-end mt-2">
                    <a href="{{ route('music-plan-view', ['musicPlan' => $musicPlan]) }}" target="_blank" class="inline-block">
                        <flux:button size="xs" variant="outline" color="blue" icon="eye">
                            Megtekintés
                        </flux:button>
                    </a>
                </div>

                @endif
            </div>

        </div>
    </div>

    <!-- Liturgical details -->
    <div class="p-3 space-y-2">
        @php
        $firstCelebration = $musicPlan->celebrations->first();
        @endphp

        <!-- Liturgical year and season info -->
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if($firstCelebration && ($firstCelebration->year_letter || $firstCelebration->year_parity))
            <div>
                <span class="text-neutral-600 dark:text-neutral-400">Liturgikus év:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ $firstCelebration->year_letter ?? '–' }}
                    @if($firstCelebration->year_parity)
                    ({{ $firstCelebration->year_parity }})
                    @endif
                </span>
            </div>
            @endif

            @if($firstCelebration && $firstCelebration->season_text)
            <div>
                <span class="text-neutral-600 dark:text-neutral-400">Időszak:</span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $firstCelebration->season_text }}</span>
            </div>
            @endif

            @if($firstCelebration && $firstCelebration->week)
            <flux:badge color="green" size="xs">{{ $firstCelebration->week }}. hét</flux:badge>
            @endif

            @if($musicPlan->day_name)
            <flux:badge color="purple" size="xs">{{ $musicPlan->day_name }}</flux:badge>
            @endif
        </div>

        <!-- Private notes (owner only) -->
        @if($isOwner && $musicPlan->private_notes)
        <div class="pt-1 border-t border-gray-200 dark:border-gray-700">
            <flux:heading size="xs" class="text-neutral-600 dark:text-neutral-400 mb-0.5">Privát megjegyzéseid</flux:heading>
            <flux:text class="text-xs text-gray-700 dark:text-gray-300 line-clamp-2">
                {{ Str::limit($musicPlan->private_notes, 150) }}
            </flux:text>
        </div>
        @endif

        <!-- Plan slots summary -->
        <div class="pt-1 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-1.5">
                <flux:heading size="xs" class="text-neutral-600 dark:text-neutral-400">Énekrend elemei</flux:heading>
                <flux:badge color="zinc" size="xs">{{ count($planSlots) }}</flux:badge>
            </div>

            @if(!empty($planSlots))
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach($planSlots as $slot)
                <div class="flex gap-1.5 p-1.5 rounded-md bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-semibold text-xs flex-shrink-0">
                        {{ $slot['sequence'] }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:heading size="xs" class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                            {{ $slot['name'] }}
                        </flux:heading>
                        @if($slot['description'])
                        <flux:text class="text-xs text-gray-600 dark:text-gray-400 line-clamp-1">
                            {{ Str::limit($slot['description'], 60) }}
                        </flux:text>
                        @endif

                        @if(!empty($slot['assignments']))
                        <div class="mt-0.5 space-y-0.5">
                            @foreach($slot['assignments'] as $assignment)
                            @if(!empty($assignment['music']))
                            <div class="text-xs">
                                @if(!empty($assignment['scope_label']))
                                <flux:badge color="zinc" size="xs" class="mb-0.5">{{ $assignment['scope_label'] }}</flux:badge>
                                @endif
                                <div class="text-gray-700 dark:text-gray-300 font-medium truncate">
                                    {{ $assignment['music']->title }}
                                </div>
                                @if($assignment['music']->subtitle)
                                <div class="text-gray-600 dark:text-gray-400 line-clamp-1">
                                    {{ Str::limit($assignment['music']->subtitle, 50) }}
                                </div>
                                @endif
                                @if(!empty($assignment['notes']))
                                <div class="text-gray-500 dark:text-gray-500 italic line-clamp-1">
                                    {{ Str::limit($assignment['notes'], 50) }}
                                </div>
                                @endif
                            </div>
                            @endif
                            @endforeach
                        </div>
                        @else
                        <flux:text class="text-xs text-gray-500 dark:text-gray-400 italic mt-0.5">
                            Nincs zene
                        </flux:text>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <flux:callout variant="secondary" icon="musical-note" class="text-xs py-1.5">
                Ehhez az énekrendhez még nem adtál elemeket.
            </flux:callout>
            @endif
        </div>

        <!-- Metadata footer -->
        <div class="flex items-center gap-3 pt-1 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-1">
                <flux:icon name="musical-note" class="h-3 w-3" variant="mini" />
                <span>{{ $musicPlan->musicAssignments()->count() }}</span>
            </div>
            <div class="flex items-center gap-1">
                <flux:icon name="calendar-days" class="h-3 w-3" variant="mini" />
                <span>{{ $musicPlan->created_at->translatedFormat('Y. m. d.') }}</span>
            </div>
            @if($musicPlan->actual_date)
            <flux:link
                href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ $musicPlan->actual_date->format('Y-m-d') }}"
                target="_blank"
                class="text-xs hover:underline"
                onclick="event.stopPropagation()">
                Igenaptár
            </flux:link>
            @endif
        </div>
    </div>
</div>