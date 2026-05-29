<?php

namespace App\Http\Controllers;

use App\Models\Score;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreIncipitController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Score $score): StreamedResponse
    {
        $this->authorize('view', $score);

        abort_unless(Storage::exists($score->incipit_path), 404);

        $lastModified = new \DateTimeImmutable('@'.Storage::lastModified($score->incipit_path));

        $response = Storage::response($score->incipit_path, headers: ['Content-Type' => 'image/png']);
        $response->setLastModified($lastModified);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified(request());

        return $response;
    }
}
