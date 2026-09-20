<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScreenAcknowledgementRequest;
use App\Models\Presentation;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ScreenAcknowledgementController extends Controller
{
    public function __invoke(ScreenAcknowledgementRequest $request, Screen $screen): JsonResponse
    {
        $presentation = Presentation::query()
            ->mine($request->user())
            ->find($request->integer('presentationId'));

        abort_unless($presentation instanceof Presentation, 404);

        if ($presentation->ended_at !== null || $request->integer('appliedVersion') > $presentation->version) {
            throw ValidationException::withMessages([
                'appliedVersion' => __('This presentation state is no longer current.'),
            ]);
        }

        abort_unless(Presentation::currentFor($request->user())?->is($presentation), 409);

        $screen = $screen->acknowledge(
            $presentation,
            $request->integer('appliedVersion'),
            $request->input('drawnRevision'),
        );

        Log::info('Projection screen acknowledged presentation state.', [
            'screen_id' => $screen->id,
            'presentation_id' => $presentation->id,
            'applied_version' => $screen->applied_version,
        ]);

        return response()->json([
            'presentationId' => $screen->applied_presentation_id,
            'appliedVersion' => $screen->applied_version,
            'drawnRevision' => $screen->drawn_revision,
            'appliedAt' => $screen->applied_at?->toIso8601String(),
        ]);
    }
}
