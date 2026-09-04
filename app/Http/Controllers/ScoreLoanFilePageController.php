<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\Loan;
use App\Services\ScoreFileResponder;
use App\Services\LoanAccessService;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page image of an uploaded score, reached through a grant. Access is derived
 * from the grant on every request, so revoking it closes this URL too.
 */
class ScoreLoanFilePageController extends Controller
{
    public function __invoke(
        LoanAccessService $loanAccess,
        ScoreFileResponder $responder,
        string $token,
        Score $score,
        ScoreFile $scoreFile,
        int $page,
    ): Response {
        $loan = $loanAccess->resolve($token);
        abort_if(! $loan instanceof Loan, 404);
        abort_unless($loanAccess->grantsScore($loan, $score), 404);

        abort_unless($scoreFile->score_id === $score->id, 404);
        abort_unless($scoreFile->hasPage($page), 404);

        return $responder->page($scoreFile, $page, public: false);
    }
}
