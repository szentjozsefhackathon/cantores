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

        return Storage::response($score->incipit_path, headers: ['Content-Type' => 'image/png']);
    }
}
