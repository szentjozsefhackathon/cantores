<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Services\ScoreFileResponder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ScorePublicIncipitController extends Controller
{
    public function __invoke(ScoreFileResponder $responder, Score $score): Response
    {
        // 404 rather than 403: a 403 would confirm that a score exists at this
        // id, which is an oracle over every private score on the site.
        abort_unless($score->public_preview, 404);

        if (! Storage::exists($score->incipit_path)) {
            // A file-backed score keeps its incipit encrypted beside the file
            // rather than in the shared plaintext incipits/ directory.
            $file = $score->incipitFile();

            abort_unless($file !== null, 404);

            return $responder->thumbnail($file, public: true);
        }

        $lastModified = new \DateTimeImmutable('@'.Storage::lastModified($score->incipit_path));

        $response = Storage::response($score->incipit_path, headers: ['Content-Type' => 'image/png']);
        $response->setLastModified($lastModified);

        // Bounded, not immutable: clearing public_preview has to take effect,
        // and the ?v= cache-buster only covers edits. Past the fresh window the
        // reader still gets the cached bytes at once while the browser
        // revalidates behind them, which is what keeps a page of incipit strips
        // from opening a conditional request per image.
        $response->setPublic();
        $response->setMaxAge(ScoreFileResponder::PUBLIC_MAX_AGE);
        $response->setStaleWhileRevalidate(ScoreFileResponder::PUBLIC_STALE_WHILE_REVALIDATE);
        $response->isNotModified(request());

        return $response;
    }
}
