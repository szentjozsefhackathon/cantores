<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\ScoreFileResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;

class ScoreFileDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ScoreFileResponder $responder, Score $score, ScoreFile $scoreFile): Response
    {
        $this->authorize('view', $score);

        abort_unless($scoreFile->score_id === $score->id, 404);

        return $responder->download($scoreFile);
    }
}
