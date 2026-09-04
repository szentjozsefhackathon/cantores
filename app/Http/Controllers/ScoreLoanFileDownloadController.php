<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\Loan;
use App\Services\ScoreFileResponder;
use App\Services\LoanAccessService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The original uploaded file, reached through a grant.
 *
 * Reading a shared score and walking away with the publisher's file are
 * different things, so the grant carries `allow_download` and this is the only
 * place it decides anything.
 */
class ScoreLoanFileDownloadController extends Controller
{
    public function __invoke(
        LoanAccessService $loanAccess,
        ScoreFileResponder $responder,
        string $token,
        Score $score,
        ScoreFile $scoreFile,
    ): Response {
        $loan = $loanAccess->resolve($token);
        abort_if(! $loan instanceof Loan, 404);
        abort_unless($loanAccess->grantsScore($loan, $score), 404);
        abort_unless($loan->allow_download, 403);

        abort_unless($scoreFile->score_id === $score->id, 404);

        return $responder->download($scoreFile);
    }
}
