<?php

namespace App\Services;

use App\Models\Presentation;
use App\Models\Screen;
use App\Models\User;
use App\Support\DeviceId;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * What a person's show looks like to the devices following it.
 *
 * One answer rather than two, because the wall polls this about once a second
 * while a service is going on and a second request for "and where is the service
 * now" would double that for nothing. So the presentation's own state is nested
 * here when there is one, and the two URLs that belong to it travel with it:
 * a client whose show has just been switched to a different deck needs exactly
 * those, and having the server say them is cheaper than teaching the browser to
 * build them.
 *
 * `presentationId` is the field everything turns on. It changing is the one
 * event that makes a page go black and engrave something else.
 *
 * Alongside it, the screens: which of this person's devices are facing a room,
 * so the remote can say where the show is on and aim the fit panel, and so the
 * wall can read its own fit back.
 */
class ShowState
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
     *     screens: list<array{id: int, label: string, fit: array{scale: float, x: float, y: float}, fitUrl: string, isThisDevice: bool}>,
     * }
     */
    public function answer(User $user, ?Presentation $presentation, ?EloquentCollection $screens = null): array
    {
        $screens = self::describe($screens ?? self::screensFor($user));

        if (! $presentation instanceof Presentation) {
            return [
                'presentationId' => null,
                'projectionId' => null,
                'title' => null,
                'stateUrl' => null,
                'payloadUrl' => null,
                'editUrl' => null,
                'deckUrls' => null,
                'state' => null,
                'screens' => $screens,
            ];
        }

        return [
            'presentationId' => $presentation->id,
            'projectionId' => $presentation->projection_id,
            'title' => $presentation->projection->title,
            'stateUrl' => route('presentations.state', ['presentation' => $presentation->id]),
            'payloadUrl' => route('presentations.payload', ['presentation' => $presentation->id]),
            // Where the deck is changed for good rather than for today. The
            // remote's own pane offers it, and the deck in a show is swapped
            // without the page reloading, so it travels with the other two.
            'editUrl' => route('projections.edit', ['projection' => $presentation->projection_id]),
            // And where the remote changes it from the phone, for the same
            // reason: a deck swapped under the page brings its own addresses.
            'deckUrls' => self::deckUrls($presentation->projection_id),
            'state' => $this->presentations->answer($presentation),
            'screens' => $screens,
        ];
    }

    /**
     * The screens this person has facing a room right now.
     *
     * A device its owner said is not a screen is left out — it is no place to
     * say the show is on, nor to line a picture up — except when it is the
     * device asking, because a wall still needs its own fit back whatever it
     * has been called.
     *
     * @return EloquentCollection<int, Screen>
     */
    public static function screensFor(User $user): EloquentCollection
    {
        $device = DeviceId::current();

        return Screen::query()
            ->live()
            ->mine($user)
            ->where(fn ($query) => $query->offered($user)->orWhere('device_id', $device))
            ->withDeviceName($user)
            ->latest('last_seen_at')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Screen>  $screens
     * @return list<array{id: int, label: string, fit: array{scale: float, x: float, y: float}, fitUrl: string, isThisDevice: bool}>
     */
    public static function describe(EloquentCollection $screens): array
    {
        $device = DeviceId::current();

        return $screens
            ->map(fn (Screen $screen): array => [
                'id' => $screen->id,
                'label' => $screen->label(),
                'fit' => $screen->fit(),
                'fitUrl' => route('screens.fit', ['screen' => $screen->id]),
                'isThisDevice' => $screen->device_id === $device,
            ])
            ->values()
            ->all();
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
