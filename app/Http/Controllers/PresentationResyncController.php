<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Services\ShowStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PresentationResyncController extends Controller
{
    public function __invoke(ShowStream $stream, Presentation $presentation): JsonResponse
    {
        abort_unless(Gate::allows('view', $presentation->projection), 404);
        abort_unless(Gate::allows('view', $presentation), 404);

        $stream->resync($presentation);

        return response()->json([
            'presentationId' => $presentation->id,
            'version' => $presentation->version,
        ], 202);
    }
}
