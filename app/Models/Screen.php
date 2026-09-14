<?php

namespace App\Models;

use App\Support\DeviceDescription;
use Carbon\CarbonImmutable;
use Database\Factories\ScreenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * One browser that is showing the room something.
 *
 * A Projection is the deck, a Presentation is that deck being shown once, and
 * this is the thing they are shown *on*. Without it the phone could only follow
 * a deck the laptop had already chosen, because a presentation came into
 * existence by a laptop opening a URL that named one: there was no address for
 * "the wall", so the remote had to guess which deck the room was seeing.
 *
 * With it, all three of the awkward answers become one. A screen showing nothing
 * is a state the phone can say out loud; a screen the phone points somewhere
 * else is how a deck is started and switched; and leaving the remote stops
 * meaning "fall out of the only page that knew anything".
 *
 * Nothing is paired to make one. Both devices already hold a session for the
 * same person — that is what the QR sign-in was for — so claiming a screen is
 * not a ceremony between two devices but one device saying "I am the one facing
 * the room", which it says by opening the page only a wall would open.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $device_pairing_id
 * @property string $session_id
 * @property string|null $user_agent
 * @property int|null $presentation_id
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read DevicePairing|null $devicePairing
 * @property-read Presentation|null $presentation
 *
 * @method static \Database\Factories\ScreenFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen live()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen query()
 *
 * @mixin \Eloquent
 */
class Screen extends Model
{
    /** @use HasFactory<ScreenFactory> */
    use HasFactory;

    /**
     * How long a screen nobody has heard from is still counted as waiting.
     *
     * The same number as a presentation's, and for the same reason: a screen
     * aged out by a moment of bad signal is a phone saying the laptop was never
     * started, in a building where the laptop is across the room and cannot be
     * checked.
     */
    public const STALE_MINUTES = 5;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_pairing_id',
        'session_id',
        'user_agent',
        'presentation_id',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Which borrowed screen this is, where the laptop was signed in from a
     * phone. A screen may have none: a laptop signed in with a password is a
     * screen in exactly the same way.
     */
    public function devicePairing(): BelongsTo
    {
        return $this->belongsTo(DevicePairing::class);
    }

    /**
     * What the room is looking at, or nothing.
     */
    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    /**
     * This browser, as the screen it is — claimed on the way into the page only
     * a wall opens.
     *
     * Keyed by the session, because the session cookie is what makes a browser
     * that browser: reloading the page is the same screen, and so, deliberately,
     * is a second window of the same browser, because it is the same room.
     */
    public static function claimFor(
        User $user,
        string $sessionId,
        ?int $devicePairingId = null,
        ?string $userAgent = null,
    ): self {
        $screen = self::query()->firstOrNew(['session_id' => $sessionId]);

        $screen->forceFill([
            'user_id' => $user->getKey(),
            'device_pairing_id' => $devicePairingId,
            'user_agent' => $userAgent,
            'last_seen_at' => Carbon::now(),
        ])->save();

        return $screen;
    }

    /**
     * Put a deck on this screen, or take what is on it off.
     *
     * Clearing ends the presentation as well, because a screen showing nothing
     * is the end of the service and there is no one left to say so: the laptop
     * is across the building and the phone has just said it deliberately.
     */
    public function point(?Presentation $presentation): void
    {
        $previous = $this->presentation;

        $this->forceFill([
            'presentation_id' => $presentation?->getKey(),
            'last_seen_at' => Carbon::now(),
        ])->save();

        if ($presentation === null && $previous instanceof Presentation) {
            $previous->end();
        }

        $this->setRelation('presentation', $presentation);
    }

    /**
     * What the room is looking at, once a presentation that has quietly ended is
     * counted as nothing.
     *
     * The column is left alone rather than nulled here: a read is not the moment
     * to write, and the next thing pointed at this screen overwrites it anyway.
     */
    public function showing(): ?Presentation
    {
        $presentation = $this->presentation;

        return $presentation instanceof Presentation && $presentation->isLive() ? $presentation : null;
    }

    /**
     * Note that the screen's own browser is still there.
     *
     * Its own browser and no other: the read that carries this is made by the
     * phone too, and a phone polling a laptop that has been closed must not keep
     * the laptop looking alive. That is the whole difference between a heartbeat
     * and a page view.
     */
    public function touchLastSeen(): void
    {
        $now = Carbon::now();

        $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update(['last_seen_at' => $now]);

        $this->setAttribute('last_seen_at', $now)->syncOriginalAttribute('last_seen_at');
    }

    public function isLive(): bool
    {
        // A screen with no pairing was signed in with a password, and the null
        // short-circuit is the right answer for it: nothing was revoked.
        return $this->last_seen_at->gt(Carbon::now()->subMinutes(self::STALE_MINUTES))
            && $this->devicePairing?->revoked_at === null;
    }

    public function describeDevice(): string
    {
        return DeviceDescription::of($this->user_agent);
    }

    /**
     * The screens waiting right now — a closed tab ageing out rather than
     * depending on an event browsers do not reliably give, and a borrowed screen
     * going the moment its pairing is revoked rather than lingering as a live
     * row pointing at a laptop that has been signed out.
     *
     * @param  Builder<Screen>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('last_seen_at', '>', Carbon::now()->subMinutes(self::STALE_MINUTES))
            ->whereDoesntHave('devicePairing', function (Builder $pairing): void {
                $pairing->whereNotNull('revoked_at');
            });
    }

    /**
     * @param  Builder<Screen>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
