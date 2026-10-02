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
 *
 * The answer belongs to a *person* and to no device, and that is what makes it
 * publishable. A wall and a remote following the same show are the same client
 * with different controls, so what they are told is the same thing; the two
 * places it used to be worked out per device — which screen is this one, and
 * which screens this one may be shown — are decided in the browser instead,
 * out of `deviceId` and the two flags each screen carries. What is left is one
 * description per person, which can be built once and pushed down the hub
 * rather than built again for every device that asks.
 *
 * @see \App\Services\ShowStream, which publishes exactly this
 * @see resources/js/projection-follow.js screensFor()
 */
class ShowState
{
    public function __construct(private PresentationState $presentations) {}

    /**
     * This person's show, as every one of their devices is told it.
     *
     * Nothing about the device asking reaches it, which is what lets the hub
     * carry it: one description is built when the show moves and pushed to
     * whoever is listening, instead of every device coming back to find out
     * what it already could have been told.
     *
     * @return array{
     *     presentationId: int|null,
     *     projectionId: int|null,
     *     title: string|null,
     *     stateUrl: string|null,
     *     resyncUrl: string|null,
     *     payloadUrl: string|null,
     *     editUrl: string|null,
     *     deckUrls: array<string, string>|null,
     *     state: array<string, mixed>|null,
     *     screens: list<array<string, mixed>>,
     * }
     */
    public function forUser(User $user): array
    {
        return $this->answer($user, Presentation::currentFor($user));
    }

    /**
     * @return array{
     *     presentationId: int|null,
     *     projectionId: int|null,
     *     title: string|null,
     *     stateUrl: string|null,
     *     resyncUrl: string|null,
     *     payloadUrl: string|null,
     *     editUrl: string|null,
     *     deckUrls: array<string, string>|null,
     *     state: array<string, mixed>|null,
     *     screens: list<array<string, mixed>>,
     * }
     */
    public function answer(User $user, ?Presentation $presentation, ?EloquentCollection $screens = null): array
    {
        $screens = self::describe($screens ?? self::allFor($user));

        if (! $presentation instanceof Presentation) {
            return [
                'presentationId' => null,
                'projectionId' => null,
                'title' => null,
                'stateUrl' => null,
                'resyncUrl' => null,
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
            'resyncUrl' => route('presentations.resync', ['presentation' => $presentation->id]),
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
     * Everything this person has that is still a screen at all, whoever is
     * asking.
     *
     * Deliberately not narrowed to the device asking: this is what is described
     * to all of them, so the two facts that narrowing turned on travel with
     * each row instead — whether its owner is willing to be offered it, and
     * whether its own browser is actually up. A device then keeps the rows it
     * may see, which is what `screensFor()` does here and `screensFor()` does
     * in the browser.
     *
     * @return EloquentCollection<int, Screen>
     */
    public static function allFor(User $user): EloquentCollection
    {
        return Screen::query()
            ->live()
            ->mine($user)
            ->withDeviceName($user)
            ->latest('last_seen_at')
            ->get();
    }

    /**
     * The screens this person has facing a room right now, as *this* device
     * should be shown them.
     *
     * A device its owner said is not a screen is left out — it is no place to
     * say the show is on, nor to line a picture up — except when it is the
     * device asking, because a wall still needs its own fit back whatever it
     * has been called.
     *
     * The device asking counts only while its wall is actually up. A laptop
     * with the wall in one window and the remote in the other is a screen, and
     * the remote must say so; a phone that pressed Present a minute ago and
     * came back is not.
     *
     * Applied in PHP rather than in SQL, over the rows `allFor()` read, because
     * the same rule has to be applied in the browser to a description that was
     * built for nobody in particular — and a rule written twice is a rule that
     * drifts. Here it is written once more, against the same two predicates.
     *
     * @return EloquentCollection<int, Screen>
     */
    public static function screensFor(User $user, ?EloquentCollection $screens = null): EloquentCollection
    {
        $device = DeviceId::current();

        return ($screens ?? self::allFor($user))
            ->filter(fn (Screen $screen): bool => $screen->device_id === $device
                ? $screen->isPresenting()
                : $screen->isOffered())
            ->values();
    }

    /**
     * One person's screens, said once for all of their devices.
     *
     * `offered` and `presenting` are the two facts a device needs to work out
     * which of these it may be shown; `responding` is the one the remote draws
     * "screen not responding" from, and it is sent as an answer rather than as
     * a timestamp on purpose. A clock in the body would move every fifteen
     * seconds for every screen, and this body is compared with its own last
     * version on every poll — so a field that ticks is a field that costs a
     * full answer down the wire during the quietest part of a Mass.
     *
     * @param  EloquentCollection<int, Screen>  $screens
     * @return list<array<string, mixed>>
     */
    public static function describe(EloquentCollection $screens): array
    {
        return $screens
            ->map(fn (Screen $screen): array => [
                'id' => $screen->id,
                'deviceId' => $screen->device_id,
                'label' => $screen->label(),
                'fit' => $screen->fit(),
                'fitUrl' => route('screens.fit', ['screen' => $screen->id]),
                'offered' => $screen->isOffered(),
                'presenting' => $screen->isPresenting(),
                'responding' => $screen->isResponding(),
                'appliedPresentationId' => $screen->applied_presentation_id,
                'appliedVersion' => $screen->applied_version,
                'drawnRevision' => $screen->drawn_revision,
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
