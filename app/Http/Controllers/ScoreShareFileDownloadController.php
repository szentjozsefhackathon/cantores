<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\Share;
use App\Services\ScoreFileResponder;
use App\Services\ShareAccessService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The original uploaded file, reached through a grant.
 *
 * Reading a shared score and walking away with the publisher's file are
 * different things, so the grant carries `allow_download` and this is the only
 * place it decides anything.
 */
class ScoreShareFileDownloadController extends Controller
{
    public function __invoke(
        ShareAccessService $shareAccess,
        ScoreFileResponder $responder,
        string $token,
        Score $score,
        ScoreFile $scoreFile,
    ): Response {
        $share = $shareAccess->resolve($token);
        abort_if(! $share instanceof Share, 404);
        abort_unless($shareAccess->grantsScore($share, $score), 404);
        abort_unless($share->allow_download, 403);

        abort_unless($scoreFile->score_id === $score->id, 404);

        return $responder->download($scoreFile);
    }
}
