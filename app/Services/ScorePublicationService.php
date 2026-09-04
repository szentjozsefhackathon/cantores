<?php

namespace App\Services;

use App\Enums\ScorePublicationStatus;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place a publication's status changes.
 *
 * Keeping every transition here means the invariants that make the review
 * meaningful — the approval fingerprint, the incipit flag, the cache flush —
 * cannot be forgotten by a caller that sets `status` directly.
 */
class ScorePublicationService
{
    /**
     * Nominate a score, or re-nominate one that was rejected or withdrawn.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function submit(Score $score, User $nominator, array $attributes): ScorePublication
    {
        $publication = $score->publication;

        if ($publication instanceof ScorePublication && ! $publication->status->isOwnerResubmittable()) {
            throw new RuntimeException('This score cannot be resubmitted in its current state.');
        }

        return DB::transaction(function () use ($score, $nominator, $attributes, $publication): ScorePublication {
            $payload = [
                ...$attributes,
                'status' => ScorePublicationStatus::Submitted,
                'submitted_by' => $nominator->getKey(),
                'submitted_at' => now(),
                // A fresh nomination is not carrying the previous decision.
                'reviewer_id' => null,
                'reviewed_at' => null,
                'review_notes' => null,
                'self_approved' => false,
                'approved_fingerprint' => null,
            ];

            if ($publication instanceof ScorePublication) {
                $publication->update($payload);

                return $publication;
            }

            return $score->publication()->create($payload);
        });
    }

    /**
     * Publish the score. The reviewer is recorded on the row itself, not only
     * in the audit log, so the decision is legible without one.
     */
    public function approve(ScorePublication $publication, User $reviewer, ?string $notes = null, bool $selfApproved = false): void
    {
        $score = $publication->score;

        if ($score->publishedFiles()->isEmpty() && ($score->content === null || $score->content === '')) {
            throw new RuntimeException('A publication needs either a publishable file or typed content.');
        }

        DB::transaction(function () use ($publication, $score, $reviewer, $notes, $selfApproved): void {
            $publication->update([
                'status' => ScorePublicationStatus::Approved,
                'reviewer_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'self_approved' => $selfApproved,
                'published_at' => $publication->published_at ?? now(),
                'unpublished_at' => null,
                'approved_fingerprint' => $publication->computeFingerprint(),
            ]);

            // A published score's incipit is trivially public, so the listing
            // flag follows the publication rather than being set separately.
            $score->forceFill(['public_preview' => true])->save();
        });

        $this->flushPublicCaches();
    }

    public function reject(ScorePublication $publication, User $reviewer, string $notes): void
    {
        $publication->update([
            'status' => ScorePublicationStatus::Rejected,
            'reviewer_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_notes' => $notes,
            'approved_fingerprint' => null,
        ]);

        $this->flushPublicCaches();
    }

    /**
     * The owner pulling their own score back. No fault, and they may nominate
     * it again later.
     */
    public function withdraw(ScorePublication $publication): void
    {
        $this->unpublish($publication, ScorePublicationStatus::Withdrawn);
    }

    /**
     * A reviewer pulling a published score, usually after a complaint.
     *
     * Sticky: only someone with the review permission can move it on from here,
     * so an owner cannot resubmit their way out of it.
     */
    public function takeDown(ScorePublication $publication, User $actor, string $reason): void
    {
        $this->unpublish($publication, ScorePublicationStatus::TakenDown, [
            'reviewer_id' => $actor->getKey(),
            'reviewed_at' => now(),
            'takedown_reason' => $reason,
        ]);
    }

    /**
     * Return a taken-down score to the queue for a fresh decision.
     */
    public function restore(ScorePublication $publication, User $actor): void
    {
        $publication->update([
            'status' => ScorePublicationStatus::Submitted,
            'reviewer_id' => $actor->getKey(),
            'reviewed_at' => now(),
            'takedown_reason' => null,
        ]);

        $this->flushPublicCaches();
    }

    /**
     * Drop an approval whose bytes no longer match what was reviewed.
     *
     * Called when a published file's contents change under it. The score leaves
     * the library immediately and goes back into the queue rather than being
     * rejected, since nothing has been judged yet.
     */
    public function invalidateApproval(ScorePublication $publication): void
    {
        if (! $publication->status->isPublic()) {
            return;
        }

        $publication->update([
            'status' => ScorePublicationStatus::Submitted,
            'unpublished_at' => now(),
            'approved_fingerprint' => null,
            'review_notes' => __('Returned for review: a published file changed after approval.'),
        ]);

        $this->flushPublicCaches();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function unpublish(ScorePublication $publication, ScorePublicationStatus $status, array $extra = []): void
    {
        DB::transaction(function () use ($publication, $status, $extra): void {
            $publication->update([
                ...$extra,
                'status' => $status,
                'unpublished_at' => now(),
                'approved_fingerprint' => null,
            ]);

            // Bust the `?v=` incipit URLs the moment the score stops being public.
            $publication->score->touch();
        });

        $this->flushPublicCaches();
    }

    /**
     * Drop the caches that would otherwise keep an unpublished score listed.
     */
    private function flushPublicCaches(): void
    {
        cache()->forget('sitemap');
    }
}
