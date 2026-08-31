<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\Share;
use App\Services\ShareAccessService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreShareIncipitController extends Controller
{
    /**
     * Serves the incipit for a score reached through a grant — either the direct
     * score link (/s/{token}/incipit) or a folder or plan grant that reaches it.
     */
    public function __invoke(ShareAccessService $shareAccess, string $token, ?Score $score = null): StreamedResponse
    {
        $share = $shareAccess->resolve($token);
        abort_if(! $share instanceof Share, 404);

        if ($score instanceof Score) {
            abort_unless($shareAccess->grantsScore($share, $score), 404);
        } else {
            abort_unless($share->shareable instanceof Score, 404);
            $score = $share->shareable;
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
