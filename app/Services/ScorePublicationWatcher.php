<?php

namespace App\Services;

use App\Models\Score;
use App\Models\ScorePublication;

/**
 * Notices when a published score stops being the one that was approved.
 *
 * Uploading was policed and typing was not: `PublicScoreView` renders GABC, ABC
 * and ChordPro in the reader's browser straight from `content`, `format` and
 * `settings`, and prints `urls` beside them. Review exists for copyright, so the
 * trigger is drawn on the same line — anything that can introduce someone else's
 * material re-enters the queue.
 *
 * Render settings stay outside it: a transpose or a staff size changes how the
 * same notes look and cannot introduce anyone else's work. The consequence is
 * that adjusting a transpose on a published score shows no change publicly until
 * the next submission, which the editor says where the setting is changed.
 */
class ScorePublicationWatcher
{
    public function __construct(private readonly ScorePublicationService $publications) {}

    /**
     * Re-check a score's publication after something under it changed.
     */
    public function scoreChanged(?Score $score): void
    {
        if (! $score instanceof Score) {
            return;
        }

        // Read fresh: this runs from a saved() hook, where the in-memory relation
        // may still hold what was just replaced.
        $publication = ScorePublication::query()->where('score_id', $score->getKey())->first();

        if (! $publication instanceof ScorePublication || ! $publication->status->isPublic()) {
            return;
        }

        if ($publication->matchesApprovedFingerprint()) {
            return;
        }

        $this->publications->queueChangeForReview($publication);
    }
}
