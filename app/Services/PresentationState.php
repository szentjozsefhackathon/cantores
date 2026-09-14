<?php

namespace App\Services;

use App\Models\Presentation;
use App\Models\ProjectionSlide;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * What a presentation looks like to the devices driving it.
 *
 * Small, and meant to stay small: this is the hot path of the feature, polled
 * about once a second from both ends while a service is going on, and every
 * field in it is either something a client has to act on or something it has to
 * show. Anything that could be worked out from the payload belongs in the
 * payload, which is read only when the deck has actually changed.
 *
 * The address is resolved here rather than sent raw, because only the server
 * knows what the deck now contains — but the *slide* within a row is left as the
 * clients found it, because how many slides a row comes to is read off the score
 * in the browser every time it is drawn, and no query here can answer it.
 */
class PresentationState
{
    /**
     * @return array{
     *     version: int,
     *     entryId: int|null,
     *     slideIndex: int,
     *     blanked: bool,
     *     reveals: array<int, list<int>>,
     *     revision: string,
     *     drawnRevision: string|null,
     *     endedAt: string|null,
     * }
     */
    public function answer(Presentation $presentation): array
    {
        $projection = $presentation->projection;
        $entries = $this->entriesOf($presentation);
        $address = $presentation->addressIn($entries);

        return [
            'version' => $presentation->version,
            'entryId' => $address['entryId'],
            'slideIndex' => $address['slideIndex'],
            'blanked' => $presentation->blanked,
            'reveals' => $presentation->revealsIn($entries),
            'revision' => $projection->revision(),
            'drawnRevision' => $presentation->drawn_revision,
            'endedAt' => $presentation->ended_at?->toIso8601String(),
        ];
    }

    /**
     * The deck's rows, in order, with only the two columns an address is
     * resolved against.
     *
     * @return EloquentCollection<int, ProjectionSlide>
     */
    public function entriesOf(Presentation $presentation): EloquentCollection
    {
        return $presentation->projection->entries()->get(['id', 'projection_id', 'sequence']);
    }
}
