<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScreenShapeRequest;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;

/**
 * A wall saying what shape it is.
 *
 * Sent when the page opens and when its window is resized or goes full screen,
 * so that the phone can draw the wall and line the picture up inside it rather
 * than inside a band of its own.
 */
class ScreenShapeController extends Controller
{
    public function __invoke(ScreenShapeRequest $request, Screen $screen): JsonResponse
    {
        $screen->reportShape($request->float('shape'));

        return response()->json(['shape' => $screen->shape]);
    }
}
