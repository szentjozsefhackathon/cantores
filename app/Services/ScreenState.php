<?php

namespace App\Services;

use App\Models\Presentation;
use App\Models\Screen;

/**
 * What a screen looks like to the devices watching it.
 *
 * One answer rather than two, because the wall polls this about once a second
 * while a service is going on and a second request for "and where is the service
 * now" would double that for nothing. So the presentation's own state is nested
 * here when there is one, and the two URLs that belong to it travel with it:
 * a client that has just been pointed at a different deck needs exactly those,
 * and having the server say them is cheaper than teaching the browser to build
 * them.
 *
 * `presentationId` is the field everything turns on. It changing is the one
 * event that makes a screen go black and engrave something else.
 */
class ScreenState
{
    public function __construct(private PresentationState $presentations) {}

    /**
     * @return array{
     *     presentationId: int|null,
     *     projectionId: int|null,
     *     title: string|null,
     *     stateUrl: string|null,
     *     payloadUrl: string|null,
     *     state: array<string, mixed>|null,
     * }
     */
    public function answer(Screen $screen): array
    {
        $presentation = $screen->showing();

        if (! $presentation instanceof Presentation) {
            return [
                'presentationId' => null,
                'projectionId' => null,
                'title' => null,
                'stateUrl' => null,
                'payloadUrl' => null,
                'state' => null,
            ];
        }

        return [
            'presentationId' => $presentation->id,
            'projectionId' => $presentation->projection_id,
            'title' => $presentation->projection->title,
            'stateUrl' => route('presentations.state', ['presentation' => $presentation->id]),
            'payloadUrl' => route('presentations.payload', ['presentation' => $presentation->id]),
            'state' => $this->presentations->answer($presentation),
        ];
    }
}
