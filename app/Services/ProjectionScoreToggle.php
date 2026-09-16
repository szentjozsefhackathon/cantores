<?php

namespace App\Services;

use App\Models\MusicPlanSlotAssignment;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\User;

/**
 * Add or remove one score, or one of the files it holds — the single write
 * behind every "+" and "×" a plan's own editor shows, shared with whatever else
 * is allowed to change the same deck.
 *
 * Adding checks that the viewer may actually read the score, so an id typed into
 * a request cannot pull someone else's work onto a screen, and a file id is
 * honoured only where it is one of that score's own drawable files. The
 * assignment it was chosen from rides along, and the slot with it, because that
 * is what names it and what says where it lands — under its own music, in its
 * own slot, rather than at the end.
 */
class ProjectionScoreToggle
{
    public function __construct(
        private readonly MusicPlanScoreListService $scores,
        private readonly ProjectionRenderPayload $payloads,
        private readonly PlanOutline $outline,
    ) {}

    public function toggle(Projection $projection, ?User $viewer, int $scoreId, ?int $assignmentId = null, ?int $fileId = null): void
    {
        $source = $this->scores->sourcesFor([$scoreId], $viewer)->get($scoreId);

        if ($source === null) {
            // Unreadable now — a recalled loan, an unpublished score. It cannot
            // be added, but one already standing in the deck must still be
            // removable.
            $projection->entries()->where('score_id', $scoreId)->first()?->delete();

            return;
        }

        if ($fileId !== null && ! isset($source['files'][$fileId])) {
            return;
        }

        $entries = $this->payloads->entriesOf($projection);

        $existing = $entries
            ->where('score_id', $scoreId)
            ->first(fn (ProjectionSlide $entry): bool => $this->payloads->fileOf($entry, $source)['file_id'] === ($fileId ?? $source['file_id']));

        if ($existing instanceof ProjectionSlide) {
            $existing->delete();

            return;
        }

        $assignment = $this->assignmentInPlan($projection, $assignmentId);

        $chosenScoreIds = $entries->whereNotNull('score_id')->pluck('score_id')->all();
        $sources = $this->payloads->sourcesFor($entries, $viewer);
        $chosenFileIds = $entries
            ->whereNotNull('score_id')
            ->map(function (ProjectionSlide $entry) use ($sources): ?int {
                $entrySource = $sources->get($entry->score_id);

                return $entrySource === null ? null : $this->payloads->fileOf($entry, $entrySource)['file_id'];
            })
            ->filter()
            ->values()
            ->all();

        $tree = $this->outline->for($projection, $entries, $chosenScoreIds, $chosenFileIds);
        $order = $this->outline->flatten($tree);
        $at = $this->outline->appendIndex($tree, $assignment?->music_plan_slot_plan_id, $assignment?->id);

        $entry = $projection->entries()->create([
            'score_id' => $scoreId,
            'score_file_id' => $fileId,
            'music_plan_slot_assignment_id' => $assignment?->id,
            'music_plan_slot_plan_id' => $assignment?->music_plan_slot_plan_id,
            'sequence' => (int) $projection->entries()->max('sequence') + 1,
        ]);

        array_splice($order, $at, 0, [$entry->id]);

        $keyed = $projection->entries()->get()->keyBy('id');

        foreach ($order as $position => $id) {
            $keyed[$id]?->update(['sequence' => $position]);
        }
    }

    /**
     * The assignment, but only when it really belongs to this deck's plan — a
     * heading is read by a whole congregation, and must not be borrowed from
     * somebody else's service.
     */
    private function assignmentInPlan(Projection $projection, ?int $assignmentId): ?MusicPlanSlotAssignment
    {
        if ($assignmentId === null || $projection->music_plan_id === null) {
            return null;
        }

        return MusicPlanSlotAssignment::query()
            ->where('id', $assignmentId)
            ->whereHas('musicPlanSlotPlan', fn ($query) => $query->where('music_plan_id', $projection->music_plan_id))
            ->first();
    }
}
