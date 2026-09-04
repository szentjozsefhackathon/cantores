<?php

namespace App\Services;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\ScoreVersion;
use App\Policies\ScorePublicationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The only gate on the public library, and the third access axis on a score.
 *
 * The other two are ownership (ScorePolicy) and secret links
 * (LoanAccessService). This one is deliberately separate from both, and its
 * routes are deliberately separate from the authenticated `/scores/*` ones:
 * those sit behind `auth,verified`, and that middleware is a second line of
 * defence over every private file on the site. Adding a public branch inside
 * them would delete that defence for all of them at once, so one wrong
 * condition would expose the whole library. Keeping the public surface to its
 * own routes, whose single entry point is requireVisible(), makes guest
 * access safe by construction.
 *
 * That entry point admits one reader beyond the public: an editor holding the
 * review permission, previewing a nomination that is not live yet. A licence
 * cannot be judged from a form, so the reviewer reads the very page the public
 * would get. The widening is authenticated and permission-checked, so it can
 * never reach a guest, and it is confined to scores whose owner has already
 * offered them — a score with no publication row stays invisible here.
 *
 * Publication and secret links never consult each other: revoking a share does
 * not unpublish, unpublishing does not revoke a share, and `allow_download` has
 * no meaning here — per-file `is_published` governs the public path.
 */
class PublicScoreAccessService
{
    /**
     * The live publication for a score, or null when guests may not reach it.
     */
    public function published(Score $score): ?ScorePublication
    {
        $publication = $score->publication;

        return $publication instanceof ScorePublication && $publication->isPublic()
            ? $publication
            : null;
    }

    /**
     * The nomination the current user is entitled to preview, if any.
     *
     * Only a signed-in holder of the review permission, and only where the
     * owner has actually nominated the score: a submitted row, a rejected one
     * they are asked to look at again, or a taken-down one they may restore.
     */
    public function previewable(Score $score): ?ScorePublication
    {
        $publication = $score->publication;

        if (! $publication instanceof ScorePublication) {
            return null;
        }

        return Auth::user()?->can(ScorePublicationPolicy::REVIEW_PERMISSION) === true
            ? $publication
            : null;
    }

    /**
     * The publication behind this page for whoever is asking: the live one, or
     * the one a reviewer is previewing.
     */
    public function visible(Score $score): ?ScorePublication
    {
        return $this->published($score) ?? $this->previewable($score);
    }

    /**
     * Whether what the viewer is looking at is a reviewer's preview rather than
     * the live library page, so the page can say so and stay out of the index.
     *
     * Two cases, and the second one only exists because of versioning: a score
     * that is not published yet, and a published score with a correction waiting
     * in the queue. A reviewer asked to judge that correction has to be shown the
     * correction, not the version the public is reading.
     */
    public function isPreview(Score $score): bool
    {
        if (! $this->previewable($score) instanceof ScorePublication) {
            return false;
        }

        $published = $this->published($score);

        return ! $published instanceof ScorePublication || $published->hasUnpublishedChanges();
    }

    /**
     * The publication this viewer may read, or an abort.
     *
     * A takedown answers 410 so search engines drop the URL quickly; everything
     * else answers 404, because 403 would confirm the score exists.
     */
    public function requireVisible(Score $score): ScorePublication
    {
        $publication = $this->visible($score);

        if ($publication instanceof ScorePublication) {
            return $publication;
        }

        abort($score->publication?->status->httpStatusForGuest() ?? 404);
    }

    /**
     * A file this viewer may fetch: one the score offers the public, of a score
     * that is published or that they are reviewing.
     *
     * Always 404 here, never 410: the HTML page is what search engines should
     * be told about, and an artifact's status must not describe the score.
     */
    public function requireVisibleFile(Score $score, ScoreFile $scoreFile): ScoreFile
    {
        // Not requireVisible(): that answers 410 for a takedown, which would
        // let an artifact URL describe the score's history to anyone probing it.
        abort_unless($this->visible($score) instanceof ScorePublication, 404);

        abort_unless($scoreFile->score_id === $score->getKey(), 404);

        // What the public may fetch is what the approved version was approved
        // with, not what the score currently flags. The two differ exactly when a
        // file has been replaced: the old bytes are still the published page's,
        // and the new ones are not published until a reviewer says so.
        $version = $this->versionFor($score);

        if ($version instanceof ScoreVersion) {
            abort_unless($version->files()->whereKey($scoreFile->getKey())->exists(), 404);

            return $scoreFile;
        }

        // Not isPubliclyAvailable(): that asks whether the score is live, which
        // is exactly what a preview has not settled yet.
        abort_unless($scoreFile->mayBeOffered(), 404);

        return $scoreFile;
    }

    /**
     * The frozen copy behind this score's public page, if it has one.
     *
     * A publication approved before versioning existed has none, and the live
     * score stands in — the same thing the page always showed.
     */
    public function versionFor(Score $score): ?ScoreVersion
    {
        $publication = $score->publication;

        if (! $publication instanceof ScorePublication) {
            return null;
        }

        return $this->isPreview($score)
            ? $publication->submittedVersion
            : $publication->approvedVersion;
    }

    /**
     * Constrain a score query to the public library.
     *
     * @param  Builder<Score>  $query
     * @return Builder<Score>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->published();
    }
}
