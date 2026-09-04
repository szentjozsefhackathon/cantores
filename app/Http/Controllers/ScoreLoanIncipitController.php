<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\Loan;
use App\Services\ScoreFileResponder;
use App\Services\LoanAccessService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ScoreLoanIncipitController extends Controller
{
    /**
     * Serves the incipit for a score reached through a grant — either the direct
     * score link (/s/{token}/incipit) or a folder or plan grant that reaches it.
     */
    public function __invoke(
        LoanAccessService $loanAccess,
        ScoreFileResponder $responder,
        string $token,
        ?Score $score = null,
    ): Response {
        $loan = $loanAccess->resolve($token);
        abort_if(! $loan instanceof Loan, 404);

        if ($score instanceof Score) {
            abort_unless($loanAccess->grantsScore($loan, $score), 404);
        } else {
            abort_unless($loan->lendable instanceof Score, 404);
            $score = $loan->lendable;
        }

        $file = $score->incipitFile();
        if ($file instanceof ScoreFile) {
            return $responder->thumbnail($file, public: false);
        }

        abort_unless(Storage::exists($score->incipit_path), 404);

        $lastModified = new \DateTimeImmutable('@'.Storage::lastModified($score->incipit_path));

        $response = Storage::response($score->incipit_path, headers: ['Content-Type' => 'image/png']);
        $response->setLastModified($lastModified);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');
        $response->isNotModified(request());

        return $response;
    }
}
