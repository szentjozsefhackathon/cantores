<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\Share;
use App\Services\ScoreFileResponder;
use App\Services\ShareAccessService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ScoreShareIncipitController extends Controller
{
    /**
     * Serves the incipit for a score reached through a grant — either the direct
     * score link (/s/{token}/incipit) or a folder or plan grant that reaches it.
     */
    public function __invoke(
        ShareAccessService $shareAccess,
        ScoreFileResponder $responder,
        string $token,
        ?Score $score = null,
    ): Response {
        $share = $shareAccess->resolve($token);
        abort_if(! $share instanceof Share, 404);

        if ($score instanceof Score) {
            abort_unless($shareAccess->grantsScore($share, $score), 404);
        } else {
            abort_unless($share->shareable instanceof Score, 404);
            $score = $share->shareable;
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
