<?php

namespace App\Livewire\Pages;

use App\Models\MusicPlan;
use App\Services\MusicPlanScoreListService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;
    public array $planSlots = [];
    public bool $isPublished = false;
    public bool $isOwner = false;
    public bool $canEditGenre = false;

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

        // Check if current user is the owner
        $this->isOwner = Auth::check() && Auth::id() === $this->musicPlan->user_id;

        // Check if current user can edit the genre
        $this->canEditGenre = Auth::check() && Gate::allows('updateGenre', $this->musicPlan);

        // Sync published state
        $this->isPublished = !$this->musicPlan->is_private;

        // Load plan slots
        $this->loadPlanSlots();
    }

    public function rendering(View $view): void
    {
        $celebration = $this->musicPlan->celebration_name;
        $date = $this->musicPlan->actual_date?->translatedFormat('Y. F j.');

        $title = $celebration ?? 'Énekrend';
        if ($date) {
            $title .= ' – ' . $date;
        }

        $description = 'Liturgikus énekrend';
        if ($celebration) {
            $description .= ': ' . $celebration;
        }
        if ($date) {
            $description .= ' (' . $date . ')';
        }
        $description .= '. Javasolt énekek és énekrendek a Cantores.hu-n.';

        $view->layout('layouts::app.main', [
            'title'       => $title,
            'description' => $description,
            'noindex'     => $this->musicPlan->is_private,
        ]);
    }

    private function loadPlanSlots(): void
    {
        $user = Auth::user();

        // The service list: for each music, every score this reader may actually
        // see — their own, the ones they kept out of somebody's loan, and the
        // public library. Nothing is chosen and nothing is stored, so a published
        // plan needs no special case: each viewer sees what they hold.
        $scoresByMusicId = app(MusicPlanScoreListService::class)->forViewer($this->musicPlan, $user);

        $assignmentsByPivot = $this->musicPlan->musicAssignments()
            ->with([
                'music' => fn ($q) => $q->with('collections')->visibleTo($user),
                'scopes',
            ])
            ->orderBy('music_plan_slot_plan_id')
            ->orderBy('music_sequence')
            ->get()
            ->groupBy('music_plan_slot_plan_id');

        $slotsQuery = $this->musicPlan->slots()
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence');

        if ($this->musicPlan->is_private) {
            $slotsQuery->visibleToUser($user);
        }

        $this->planSlots = $slotsQuery
            ->get()
            ->map(function ($slot) use ($assignmentsByPivot, $scoresByMusicId) {
                $pivotId = $slot->pivot->id;
                $assignments = $assignmentsByPivot->get($pivotId, collect());

                return [
                    'id' => $slot->id,
                    'pivot_id' => $pivotId,
                    'name' => $slot->name,
                    'description' => $slot->description,
                    'sequence' => $slot->pivot->sequence,
                    'assignments' => $assignments->map(function ($assignment) use ($scoresByMusicId) {
                        return [
                            'id' => $assignment->id,
                            'music_id' => $assignment->music_id,
                            'music_sequence' => $assignment->music_sequence,
                            'notes' => $assignment->notes,
                            'music' => $assignment->music,
                            'scope_label' => $assignment->scope_label,
                            'scores' => $scoresByMusicId->get($assignment->music_id, collect())->all(),
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
            <div class="flex flex-wrap items-center gap-4 mb-4">
                <x-genre-icon :genre-id="$musicPlan->genre_id" />
                <flux:heading size="xl">Énekrend</flux:heading>
                <x-user-badge :user="$musicPlan->user" />

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
                    @if($canEditGenre)
                    <livewire:music-plan-editor.genre-select :music-plan="$musicPlan" />
                    @endif
                        @php
                        $firstCelebration = $musicPlan->celebration;
                        $hasLiturgicalDetails = $firstCelebration && (
                            $firstCelebration->year_letter ||
                            $firstCelebration->year_parity ||
                            $firstCelebration->season_text ||
                            $firstCelebration->week
                        );
                        @endphp
                    @if($hasLiturgicalDetails)
                    <div>
                        <div class="flex flex-row gap-2">
                            @if($firstCelebration->year_letter || $firstCelebration->year_parity)
                            <flux:badge color="zinc">{{ $firstCelebration->year_letter }} {{ $firstCelebration->year_parity ? '(' . $firstCelebration->year_parity . ')' : '' }} év</flux:badge>
                            @endif
                            @if($firstCelebration->season_text)
                            <flux:badge color="blue" size="sm">{{ $firstCelebration->season_text }}</flux:badge>
                            @endif
                            @if($firstCelebration->week)
                            <flux:badge color="green" size="sm">{{ $firstCelebration->week }}.hét</flux:badge>
                            @endif
                            @if($musicPlan->day_name)
                            <flux:badge color="purple" size="sm">{{ $musicPlan->day_name }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                @if($isOwner && $musicPlan->private_notes)
                <!-- Private notes (owner only) -->
                <div class="pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-2">Privát megjegyzéseid</flux:heading>
                    <flux:card class="p-4 bg-neutral-50 dark:bg-neutral-900/50">
                        <flux:text class="whitespace-pre-wrap">{{ $musicPlan->private_notes }}</flux:text>
                    </flux:card>
                </div>
                @endif

                <!-- Editor Columns -->
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
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($slot['description'], 120) }}</flux:text>
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
                                            />

                                            @if(!empty($assignment['scores']))
                                            <x-plan-score-list :scores="$assignment['scores']" />
                                            @endif
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
                    @if($isOwner)
                    <flux:button variant="outline" color="blue" icon="pencil" href="{{ route('music-plan-editor', $musicPlan) }}">
                        Énekrend szerkesztése
                    </flux:button>
                    @endif
                    <livewire:music-plan-loan-modal :music-plan="$musicPlan" />
                    <form method="POST" action="{{ route('music-plans.copy', $musicPlan) }}" class="inline">
                        @csrf
                        <flux:button type="submit" variant="outline" color="blue" icon="clipboard-copy">
                            Másolat készítése
                        </flux:button>
                    </form>
                </div>
            </div>
        </flux:card>
    </div>
</div>