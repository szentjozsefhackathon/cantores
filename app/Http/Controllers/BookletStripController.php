<?php

namespace App\Http\Controllers;

use App\Models\Booklet;
use App\Models\ScoreFile;
use App\Services\MusicPlanScoreListService;
use App\Services\ScoreFileResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves one system of a file-backed score to the booklet that is drawing it.
 *
 * Scoped to a booklet rather than to a score because that is where the access
 * question is already answered: a booklet may hold the owner's own scores, ones
 * borrowed through a live loan, and ones taken from the public library, and
 * MusicPlanScoreListService is what knows which of those this viewer still
 * holds. Asking it again here means a recalled loan stops serving strips at the
 * same moment it stops serving anything else, rather than at the next render.
 */
class BookletStripController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(
        MusicPlanScoreListService $scores,
        ScoreFileResponder $responder,
        Booklet $booklet,
        ScoreFile $scoreFile,
        int $page,
        int $index,
    ): Response {
        $this->authorize('update', $booklet);

        abort_if($scoreFile->isSuperseded(), 404);
        abort_unless($scoreFile->hasStrip($page, $index), 404);
        abort_if($scores->sourcesFor([$scoreFile->score_id], Auth::user())->isEmpty(), 404);

        return $responder->strip($scoreFile, $page, $index, public: false);
    }
}
