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
     *     editUrl: string|null,
     *     deckUrls: array<string, string>|null,
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
                'editUrl' => null,
                'deckUrls' => null,
                'fit' => $screen->fit(),
                'state' => null,
            ];
        }

        return [
            'presentationId' => $presentation->id,
            'projectionId' => $presentation->projection_id,
            'title' => $presentation->projection->title,
            'stateUrl' => route('presentations.state', ['presentation' => $presentation->id]),
            'payloadUrl' => route('presentations.payload', ['presentation' => $presentation->id]),
            // Where the deck is changed for good rather than for today. The
            // remote's own pane offers it, and the deck on a screen is swapped
            // without the page reloading, so it travels with the other two.
            'editUrl' => route('projections.edit', ['projection' => $presentation->projection_id]),
            // And where the remote changes it from the phone, for the same
            // reason: a deck swapped under the page brings its own addresses.
            'deckUrls' => self::deckUrls($presentation->projection_id),
            // Where the picture lands on this wall. A fact about the room and
            // not about the deck, so it is answered even by a screen showing
            // nothing — the cantor lines the beamer up before the deck is on
            // it as readily as during the first hymn.
            'fit' => $screen->fit(),
            'state' => $this->presentations->answer($presentation),
        ];
    }

    /**
     * Where the remote writes to one deck.
     *
     * @return array{scoreToggleUrl: string, moveUrl: string, addedMusicsUrl: string, musicSearchUrl: string}
     */
    public static function deckUrls(int $projectionId): array
    {
        $deck = ['projection' => $projectionId];

        return [
            'scoreToggleUrl' => route('projections.score-toggle', $deck),
            'moveUrl' => route('projections.move', $deck),
            'addedMusicsUrl' => route('projections.added-musics.store', $deck),
            'musicSearchUrl' => route('projections.music-search', $deck),
        ];
    }
}
