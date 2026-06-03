<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreShareIncipitController extends Controller
{
    public function __invoke(string $token): StreamedResponse
    {
        $score = Score::query()->where('share_token', $token)->firstOrFail();

        abort_unless(Storage::exists($score->incipit_path), 404);

        $lastModified = new \DateTimeImmutable('@'.Storage::lastModified($score->incipit_path));

        $response = Storage::response($score->incipit_path, headers: ['Content-Type' => 'image/png']);
        $response->setLastModified($lastModified);
        $response->setPublic();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified(request());

        return $response;
    }
}
