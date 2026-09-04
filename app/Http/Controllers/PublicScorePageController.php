<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\PublicScoreAccessService;
use App\Services\ScoreFileResponder;
use Symfony\Component\HttpFoundation\Response;

class PublicScorePageController extends Controller
{
    public function __invoke(
        PublicScoreAccessService $access,
        ScoreFileResponder $responder,
        Score $score,
        ScoreFile $scoreFile,
        int $page,
    ): Response {
        $access->requireVisibleFile($score, $scoreFile);

        abort_unless($scoreFile->hasPage($page), 404);

        return $responder->page($scoreFile, $page, public: true);
    }
}
