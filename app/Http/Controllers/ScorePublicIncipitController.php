<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScorePublicIncipitController extends Controller
{
    public function __invoke(Score $score): StreamedResponse
    {
        abort_unless($score->public_preview, 403);
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
