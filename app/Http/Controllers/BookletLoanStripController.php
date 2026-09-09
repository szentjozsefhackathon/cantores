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
 * One system of an uploaded score, served to a musician reading a shared booklet.
 *
 * BookletStripController's twin, differing only in who is asking: there the
 * booklet's owner, proved by the policy; here whoever holds the link, proved by
 * the token. What may be served is the same question in both, and both put it to
 * BookletRenderPayload — the booklet must really print this score, and the
 * reader must really be entitled to it — so a recalled link stops serving
 * systems at the moment it stops serving the page they belong to.
 */
class BookletLoanStripController extends Controller
{
    public function __invoke(
        LoanAccessService $loans,
        BookletRenderPayload $payload,
        ScoreFileResponder $responder,
        string $token,
        ScoreFile $scoreFile,
        int $page,
        int $index,
    ): Response {
        $loan = $loans->resolveOfType($token, Booklet::class);

        abort_if(! $loan instanceof Loan, 404);

        abort_if($scoreFile->isSuperseded(), 404);
        abort_unless($scoreFile->hasStrip($page, $index), 404);
        abort_unless($payload->drawsFile($loan->lendable, $scoreFile, Auth::user(), $loan), 404);

        return $responder->strip($scoreFile, $page, $index, public: false);
    }
}
