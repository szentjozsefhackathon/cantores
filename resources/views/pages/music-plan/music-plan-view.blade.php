<?php

namespace App\Livewire\Pages;

use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app.main')] class extends Component
{
    public MusicPlan $musicPlan;
    public array $planSlots = [];
    public bool $isPublished = false;

    public function mount($musicPlan): void
    {
        // Load existing music plan
        if (!$musicPlan instanceof MusicPlan) {
            $musicPlan = MusicPlan::findOrFail($musicPlan);
        }

        // Check authorization using Gate (supports guest users)
        if (!Gate::allows('view', $musicPlan)) {
            abort(403);
        }

        $this->musicPlan = $musicPlan;

        // Sync published state
        $this->isPublished = !$this->musicPlan->is_private;

        // Load plan slots
        $this->loadPlanSlots();
    }

    private function loadPlanSlots(): void
    {
        $user = Auth::user();
        $assignmentsByPivot = $this->musicPlan->musicAssignments()
            ->with('music.collections')
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
                        ];
                    })->all(),
                ];
            })
            ->values()
            ->all();
    }
}
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <div class="flex items-center gap-4 mb-4">
                <x-music-plan-setting-icon :genre="$musicPlan->genre" />
                <flux:heading size="xl">Énekrend</flux:heading>
                <x-user-badge :user="$musicPlan->user" />
                @if($musicPlan->actual_date)
                <div class="flex">
                <flux:icon name="external-link" />
                <flux:link href="https://igenaptar.katolikus.hu/nap/index.php?holnap={{ $musicPlan->actual_date->format('Y-m-d') }}" target="_blank">
                    Igenaptár
                </flux:link>
                </div>
                @endif

            </div>

            <div class="space-y-4">
                <!-- Combined info grid -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Ünnep neve</flux:heading>
                        <flux:text class="text-base font-semibold">{{ $musicPlan->celebration_name ?? '–' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Dátum</flux:heading>
                        <flux:text class="text-base font-semibold">
                            @if($musicPlan->actual_date)
                            {{ $musicPlan->actual_date->translatedFormat('Y. F j.') }}
                            @else
                            –
                            @endif
                        </flux:text>
                    </div>
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
                </div>

                <!-- Editor Columns -->
                <div class="pt-6 border-t border-neutral-200 dark:border-neutral-800">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-4 lg:col-span-1">
                            <div class="flex items-center justify-between">
                                <flux:heading size="lg">Énekrend elemei</flux:heading>
                                <flux:badge color="zinc" size="sm">{{ count($planSlots) }} elem</flux:badge>
                            </div>

                            @forelse($planSlots as $slot)
                            <flux:card class="p-2 flex items-start gap-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200 font-semibold">
                                    {{ $slot['sequence'] }}
                                </div>
                                <div class="flex-1 space-y-1">
                                    <flux:heading size="sm">{{ $slot['name'] }}</flux:heading>
                                    @if($slot['description'])
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($slot['description'], 120) }}</flux:text>
                                    @endif

                                    @if(!empty($slot['assignments']))
                                    <div class="mt-3 space-y-3">
                                        @foreach($slot['assignments'] as $assignment)
                                            @if(!empty($assignment['music']))
                                                <livewire:music-card
                                                    :key="'music-card-'.$assignment['id']"
                                                    :music="$assignment['music']"
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
                                    <flux:callout variant="secondary" icon="musical-note" class="mt-3">
                                        Ehhez az elemhez nincs zene hozzárendelve.
                                    </flux:callout>
                                    @endif
                                </div>
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