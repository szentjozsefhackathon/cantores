<?php

namespace App\Http\Controllers;

use App\Models\Booklet;
use App\Models\Loan;
use App\Models\ScoreFile;
use App\Services\BookletRenderPayload;
use App\Services\LoanAccessService;
use App\Services\ScoreFileResponder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * One engraved page, in vector form, served to a musician reading a shared
 * booklet.
 *
 * BookletLoanStripController's authorisation exactly, with the strip check
 * replaced by "this file keeps page N as a vector" — the same pairing the
 * owner's two endpoints have.
 */
class BookletLoanScorePageController extends Controller
{
    public function __invoke(
        LoanAccessService $loans,
        BookletRenderPayload $payload,
        ScoreFileResponder $responder,
        string $token,
        ScoreFile $scoreFile,
        int $page,
    ): Response {
        $loan = $loans->resolveOfType($token, Booklet::class);

        abort_if(! $loan instanceof Loan, 404);

        abort_if($scoreFile->isSuperseded(), 404);
        abort_unless($scoreFile->hasVectorPage($page), 404);
        abort_unless($payload->drawsFile($loan->lendable, $scoreFile, Auth::user(), $loan), 404);

        return $responder->pageVector($scoreFile, $page, public: false);
    }
}
