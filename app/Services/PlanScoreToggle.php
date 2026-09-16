<?php

namespace App\Services;

use App\Contracts\PlanAddedMusic;
use App\Contracts\PlanDocument;
use App\Contracts\PlanEntry;
use App\Models\MusicPlanSlotAssignment;
use App\Models\Projection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Add or remove one score, or one of the files it holds — the single write
 * behind every "+" and "×" a plan's own editor shows, shared with whatever else
 * is allowed to change the same document.
 *
 * A booklet and a deck are chosen in exactly the same way, so there is one of
 * these for both. Adding checks that the viewer may actually read the score, so
 * an id typed into a request cannot pull someone else's work onto a screen or a
 * page, and a file id is honoured only where it is one of that score's own
 * drawable files. The music it was chosen from rides along — the plan's
 * assignment, or a music only this document holds — and the slot with it,
 * because that is what names it and what says where it lands: under its own
 * music, in its own slot, rather than at the end.
 */
class PlanScoreToggle
{
    public function __construct(
        private readonly MusicPlanScoreListService $scores,
        private readonly PlanOutline $outline,
        private readonly PlanOrder $order,
    ) {}

    public function toggle(PlanDocument $document, ?User $viewer, int $scoreId, ?int $assignmentId = null, ?int $fileId = null, ?int $addedMusicId = null): void
    {
        $payloads = $this->payloadsFor($document);
        $source = $this->scores->sourcesFor([$scoreId], $viewer)->get($scoreId);
        $entries = $document->entries()->get();

        if ($source === null) {
            // Unreadable now — a recalled loan, an unpublished score. It cannot
            // be added, but one already standing in the document must still be
            // removable.
            $stale = $entries->firstWhere('score_id', $scoreId);

            if ($stale instanceof PlanEntry) {
                $this->order->remove($document, $this->outline->for($document, $entries), [$stale->id]);
            }

            return;
        }

        if ($fileId !== null && ! isset($source['files'][$fileId])) {
            return;
        }

        $existing = $entries
            ->where('score_id', $scoreId)
            ->first(fn (PlanEntry $entry): bool => $payloads->fileOf($entry, $source)['file_id'] === ($fileId ?? $source['file_id']));

        if ($existing instanceof PlanEntry) {
            $this->order->remove($document, $this->outline->for($document, $entries), [$existing->id]);

            return;
        }

        $addedMusic = $this->addedMusicOf($document, $addedMusicId);
        $assignment = $addedMusic === null ? $this->assignmentInPlan($document, $assignmentId) : null;
        $slotPlanId = $addedMusic?->music_plan_slot_plan_id ?? $assignment?->music_plan_slot_plan_id;

        $tree = $this->outline->for($document, $entries);
        $at = $this->outline->appendIndex($tree, $slotPlanId, $assignment?->id, $addedMusic?->id);

        /** @var PlanEntry&Model $entry */
        $entry = $document->entries()->create([
            'score_id' => $scoreId,
            'score_file_id' => $fileId,
            'music_plan_slot_assignment_id' => $assignment?->id,
            'music_plan_slot_plan_id' => $slotPlanId,
            'added_music_id' => $addedMusic?->id,
            'sequence' => (int) $document->entries()->max('sequence') + 1,
        ]);

        $this->order->insert($document, $tree, $entry->id, $at);
    }

    /**
     * The document's own music, but only when it is really this document's — a
     * heading is read by a whole congregation, and must not be borrowed from
     * somebody else's deck.
     */
    public function addedMusicOf(PlanDocument $document, ?int $addedMusicId): ?PlanAddedMusic
    {
        if ($addedMusicId === null) {
            return null;
        }

        $music = $document->addedMusics()->whereKey($addedMusicId)->first();

        return $music instanceof PlanAddedMusic ? $music : null;
    }

    /**
     * The assignment, but only when it really belongs to this document's plan.
     */
    private function assignmentInPlan(PlanDocument $document, ?int $assignmentId): ?MusicPlanSlotAssignment
    {
        $planId = $document->musicPlan()->getParentKey();

        if ($assignmentId === null || $planId === null) {
            return null;
        }

        return MusicPlanSlotAssignment::query()
            ->where('id', $assignmentId)
            ->whereHas('musicPlanSlotPlan', fn ($query) => $query->where('music_plan_id', $planId))
            ->first();
    }

    private function payloadsFor(PlanDocument $document): PlanRenderPayload
    {
        return $document instanceof Projection
            ? app(ProjectionRenderPayload::class)
            : app(BookletRenderPayload::class);
    }
}
