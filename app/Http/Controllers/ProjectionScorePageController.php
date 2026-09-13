<?php

namespace App\Http\Controllers;

use App\Models\Projection;
use App\Models\ScoreFile;
use App\Services\MusicPlanScoreListService;
use App\Services\ScoreFileResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves one engraved page to the projection that is showing it.
 *
 * The same three checks BookletScorePageController makes — the deck's own gate,
 * the file not superseded, and MusicPlanScoreListService confirming the viewer
 * still holds the score — so a recalled loan underneath this one stops serving
 * these pages at the same moment it stops serving anything else.
 *
 * It differs from the booklet's in one way: it will serve a raster page as well
 * as a vector one. A booklet only ever draws the *systems* cut out of an
 * uploaded file, and every one of those exists in both forms, so the booklet can
 * insist on vector and fall back to strips. A projection shows the whole page,
 * letterboxed into the screen, and a file rendered before the vector pipeline
 * existed has no vector page to give — refusing it would mean a scan that simply
 * cannot be projected. So vector is preferred and a picture is accepted.
 */
class ProjectionScorePageController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(
        MusicPlanScoreListService $scores,
        ScoreFileResponder $responder,
        Projection $projection,
        ScoreFile $scoreFile,
        int $page,
    ): Response {
        $this->authorize('update', $projection);

        abort_if($scoreFile->isSuperseded(), 404);
        abort_if($scores->sourcesFor([$scoreFile->score_id], Auth::user())->isEmpty(), 404);

        if ($scoreFile->hasVectorPage($page)) {
            return $responder->pageVector($scoreFile, $page, public: false);
        }

        abort_unless($scoreFile->hasPage($page), 404);

        return $responder->page($scoreFile, $page, public: false);
    }
}
