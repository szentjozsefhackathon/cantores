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
    public function __construct(private readonly ScoreVersionService $versions) {}

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
            } else {
                $publication = $score->publication()->create($payload);
            }

            // The reviewer judges a frozen copy, not whatever the score happens to
            // be when they get to it.
            $publication->update([
                'submitted_version_id' => $this->versions->snapshotFor($publication)->getKey(),
            ]);

            return $publication;
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
            $version = $publication->submittedVersion ?? $this->versions->snapshot($score);

            $publication->update([
                'status' => ScorePublicationStatus::Approved,
                'reviewer_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'self_approved' => $selfApproved,
                'published_at' => $publication->published_at ?? now(),
                'unpublished_at' => null,
                // The version the reviewer read is what goes on the shelf, and the
                // fingerprint is that version's — not the live score's, which may
                // already have moved on.
                'approved_version_id' => $version->getKey(),
                'submitted_version_id' => $version->getKey(),
                'approved_fingerprint' => $version->fingerprint(),
            ]);

            // A published score's incipit is trivially public, so the listing
            // flag follows the publication rather than being set separately.
            $score->forceFill(['public_preview' => true])->save();
        });

        $this->flushPublicCaches();

        // If the score moved on while it was in the queue, the reviewer approved a
        // version the score no longer matches: publish that one and queue the rest.
        if (! $publication->fresh()?->matchesApprovedFingerprint()) {
            $this->queueChangeForReview($publication->refresh());
        }
    }

    /**
     * Turn a nomination down.
     *
     * A change queued behind a score that is already published is a different act
     * from rejecting the score itself: the queued version is dropped and what the
     * public was already reading stays where it is.
     */
    public function reject(ScorePublication $publication, User $reviewer, string $notes): void
    {
        if ($publication->status->isPublic() && $publication->hasUnpublishedChanges()) {
            $publication->update([
                'reviewer_id' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'submitted_version_id' => $publication->approved_version_id,
            ]);

            $this->flushPublicCaches();

            return;
        }

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
     * Put a change to an approved score back in the review queue.
     *
     * Called whenever something that can carry someone else's work changes under an
     * approval: the typed source, a published file, or a score link. The score
     * stays published and the public keeps reading the approved version while the
     * change waits, so correcting a wrong accidental no longer takes the score off
     * the shelf — the site's answer to "there is an error in bar 12" is to fix the
     * error, not to remove the score.
     *
     * The exception is a publication approved before versioning existed, which has
     * no snapshot for the public to fall back on. That one is unpublished, as it
     * always was.
     */
    public function queueChangeForReview(ScorePublication $publication): void
    {
        if (! $publication->status->isPublic()) {
            return;
        }

        if ($publication->approved_version_id === null) {
            $publication->update([
                'status' => ScorePublicationStatus::Submitted,
                'unpublished_at' => now(),
                'approved_fingerprint' => null,
                'review_notes' => __('Returned for review: the score changed after approval.'),
            ]);

            $this->flushPublicCaches();

            return;
        }

        // Refreshed rather than added to while it waits: the reviewer must read the
        // current offer, and a handful of rows per score is the whole budget.
        $version = $this->versions->snapshotFor($publication);

        $publication->update([
            'submitted_version_id' => $version->getKey(),
            'submitted_at' => now(),
            'review_notes' => __('Returned for review: the score changed after approval.'),
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
