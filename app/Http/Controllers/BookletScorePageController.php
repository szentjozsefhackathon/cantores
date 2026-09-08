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
 * Serves one engraved page, in vector form, to the booklet that is drawing it.
 *
 * An exact copy of BookletStripController's authorisation — the booklet's own
 * gate, the file not superseded, and MusicPlanScoreListService confirming the
 * viewer still holds the score — with the strip check replaced by "this file
 * keeps page N as a vector". A recalled loan stops serving these pages at the
 * same moment it stops serving anything else.
 */
class BookletScorePageController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(
        MusicPlanScoreListService $scores,
        ScoreFileResponder $responder,
        Booklet $booklet,
        ScoreFile $scoreFile,
        int $page,
    ): Response {
        $this->authorize('update', $booklet);

        abort_if($scoreFile->isSuperseded(), 404);
        abort_unless($scoreFile->hasVectorPage($page), 404);
        abort_if($scores->sourcesFor([$scoreFile->score_id], Auth::user())->isEmpty(), 404);

        return $responder->pageVector($scoreFile, $page, public: false);
    }
}
