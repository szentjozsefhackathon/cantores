<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\PublicScoreAccessService;
use App\Services\ScoreFileResponder;
use Symfony\Component\HttpFoundation\Response;

class PublicScoreDownloadController extends Controller
{
    public function __invoke(
        PublicScoreAccessService $access,
        ScoreFileResponder $responder,
        Score $score,
        ScoreFile $scoreFile,
    ): Response {
        $access->requireVisibleFile($score, $scoreFile);

        $publication = $score->publication;

        $response = $responder->download($scoreFile, public: true);

        // The licence, on the bytes themselves, for anything that reads headers
        // rather than the page around them.
        $deedUrl = $publication->effectiveLicense()->deedUrl();

        if ($deedUrl !== null) {
            $response->headers->set('Link', '<'.$deedUrl.'>; rel="license"');
        }

        return $response;
    }
}
