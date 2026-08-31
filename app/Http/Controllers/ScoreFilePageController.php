<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\ScoreFileResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;

class ScoreFilePageController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ScoreFileResponder $responder, Score $score, ScoreFile $scoreFile, int $page): Response
    {
        $this->authorize('view', $score);

        abort_unless($scoreFile->score_id === $score->id, 404);
        abort_unless($scoreFile->hasPage($page), 404);

        return $responder->page($scoreFile, $page, public: false);
    }
}
