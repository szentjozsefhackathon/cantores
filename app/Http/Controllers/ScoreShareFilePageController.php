<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\Share;
use App\Services\ScoreFileResponder;
use App\Services\ShareAccessService;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page image of an uploaded score, reached through a grant. Access is derived
 * from the grant on every request, so revoking it closes this URL too.
 */
class ScoreShareFilePageController extends Controller
{
    public function __invoke(
        ShareAccessService $shareAccess,
        ScoreFileResponder $responder,
        string $token,
        Score $score,
        ScoreFile $scoreFile,
        int $page,
    ): Response {
        $share = $shareAccess->resolve($token);
        abort_if(! $share instanceof Share, 404);
        abort_unless($shareAccess->grantsScore($share, $score), 404);

        abort_unless($scoreFile->score_id === $score->id, 404);
        abort_unless($scoreFile->hasPage($page), 404);

        return $responder->page($scoreFile, $page, public: false);
    }
}
