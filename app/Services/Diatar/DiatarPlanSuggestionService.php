<?php

namespace App\Services\Diatar;

use App\Enums\DiatarSyncStatus;
use App\Models\DiatarSyncRun;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;

class DiatarPlanSuggestionService
{
    public function __construct(private DiatarCandidateResolver $resolver) {}

    /**
     * @return array{catalog_sync_run_id: ?int, catalog_revision: ?string, rows: list<array<string, mixed>>}
     */
    public function forPlan(MusicPlan $musicPlan): array
    {
        $assignments = MusicPlanSlotAssignment::query()
            ->select('music_plan_slot_assignments.*')
            ->join('music_plan_slot_plan', 'music_plan_slot_plan.id', '=', 'music_plan_slot_assignments.music_plan_slot_plan_id')
            ->where('music_plan_slot_plan.music_plan_id', $musicPlan->id)
            ->with([
                'music.collections.diatarBooks.songs.slides',
                'music.diatarBindings' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['song.book', 'song.slides', 'slides.slide']),
            ])
            ->orderBy('music_plan_slot_plan.sequence')
            ->orderBy('music_plan_slot_assignments.music_sequence')
            ->orderBy('music_plan_slot_assignments.id')
            ->get();

        $syncRun = DiatarSyncRun::query()
            ->whereIn('status', [DiatarSyncStatus::Completed, DiatarSyncStatus::CompletedWithWarnings])
            ->latest('completed_at')
            ->latest('id')
            ->first(['id', 'source_revision']);

        return [
            'catalog_sync_run_id' => $syncRun?->id,
            'catalog_revision' => $syncRun?->source_revision,
            'rows' => $assignments->map(function (MusicPlanSlotAssignment $assignment): array {
                $resolution = $this->resolver->resolve($assignment->music);

                return [
                    'assignment_id' => $assignment->id,
                    'music_id' => $assignment->music_id,
                    'music_title' => $assignment->music->title,
                    ...$resolution,
                ];
            })->all(),
        ];
    }
}
