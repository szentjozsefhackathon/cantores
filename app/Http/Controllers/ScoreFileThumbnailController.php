<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\ScoreFileResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * The incipit crop of one file, as opposed to the one standing for the whole
 * score — the editor's file list shows a thumbnail per row.
 */
class ScoreFileThumbnailController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ScoreFileResponder $responder, Score $score, ScoreFile $scoreFile): Response
    {
        $this->authorize('view', $score);

        abort_unless($scoreFile->score_id === $score->id, 404);
        abort_unless($scoreFile->has_thumbnail, 404);

        return $responder->thumbnail($scoreFile, public: false);
    }
}
