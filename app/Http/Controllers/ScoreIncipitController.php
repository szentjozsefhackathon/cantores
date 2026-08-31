<?php

namespace App\Http\Controllers;

use App\Models\Score;
use App\Models\ScoreFile;
use App\Services\ScoreFileResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ScoreIncipitController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(ScoreFileResponder $responder, Score $score): Response
    {
        $this->authorize('view', $score);

        $file = $score->incipitFile();
        if ($file instanceof ScoreFile) {
            return $responder->thumbnail($file, public: false);
        }

        abort_unless(Storage::exists($score->incipit_path), 404);

        $lastModified = new \DateTimeImmutable('@'.Storage::lastModified($score->incipit_path));

        $response = Storage::response($score->incipit_path, headers: ['Content-Type' => 'image/png']);
        $response->setLastModified($lastModified);
        $response->setPrivate();
        $response->setMaxAge(31536000);
        $response->headers->addCacheControlDirective('immutable');
        $response->isNotModified(request());

        return $response;
    }
}
