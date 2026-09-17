<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScreenFitRequest;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;

/**
 * Line the picture up on one wall.
 *
 * The one write still addressed to a device. Pressed a dozen times while a
 * beamer is lined up and never again during the service, and it says nothing
 * about what is on the wall.
 */
class ScreenFitController extends Controller
{
    public function __invoke(ScreenFitRequest $request, Screen $screen): JsonResponse
    {
        $screen->adjustFit($request->fit());

        return response()->json(['fit' => $screen->fit()]);
    }
}
