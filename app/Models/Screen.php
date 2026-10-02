<?php

namespace App\Models;

use App\Services\ShowStream;
use App\Support\DeviceDescription;
use Carbon\CarbonImmutable;
use Database\Factories\ScreenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One browser that is showing the room something.
 *
 * A Projection is the deck, a Presentation is that deck being shown once, and
 * this is the thing they are shown *on*. Without it the phone could only follow
 * a deck the laptop had already chosen, because a presentation came into
 * existence by a laptop opening a URL that named one: there was no address for
 * "the wall", so the remote had to guess which deck the room was seeing.
 *
 * It points at nothing. A screen always shows its owner's show — the one
 * presentation they have up — so which deck the wall displays follows from
 * `user_id` alone, and the parish laptop a second cantor signs in on becomes
 * their wall and stops being anybody else's. What is left here is what really
 * is about the device: that it is there, what it is called, and where the
 * picture lands on the projector it is plugged into.
 *
 * Nothing is paired to make one. Both devices already hold a session for the
 * same person — that is what the QR sign-in was for — so claiming a screen is
 * not a ceremony between two devices but one device saying "I am the one facing
 * the room", which it says by opening the page only a wall would open.
 *
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property int|null $device_pairing_id
 * @property string $session_id
 * @property string|null $user_agent
 * @property float $fit_scale
 * @property float $fit_x
 * @property float $fit_y
 * @property int|null $applied_presentation_id
 * @property int $applied_version
 * @property string|null $drawn_revision
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read DevicePairing|null $devicePairing
 * @property-read DeviceName|null $deviceName
 * @property-read Presentation|null $appliedPresentation
 *
 * @method static \Database\Factories\ScreenFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen live()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen offered(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Screen withDeviceName(?\App\Models\User $user = null)
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
     * How stale `last_seen_at` is allowed to get before it costs a write.
     *
     * Far inside the window it is read against, so a screen is never anywhere
     * near being aged out by the saving, and still a fifteenth of the asking.
     *
     * This is now the *only* thing that says a wall is still there. The
     * acknowledgement used to say it too, every ten seconds, whether anything
     * had happened or not; it is an event again, so the column it kept warm
     * has to be warm enough on its own for the phone to notice a wall that
     * stopped answering.
     *
     * @see \App\Http\Middleware\EnforcePairedDeviceSession, which does the
     * same thing to the same column for the same reason.
     */
    public const SEEN_EVERY_SECONDS = 15;

    /**
     * How long a screen may say nothing before the remote calls it silent.
     *
     * Three heartbeats, so a wall is never called silent for missing one — and
     * a wall that really has gone is named on the phone within a verse rather
     * than within the five minutes it takes to age out of the list entirely.
     */
    public const SILENT_SECONDS = self::SEEN_EVERY_SECONDS * 3;

    /**
     * How recently this browser's own screen must have been heard from to count
     * as showing the show to the browser asking.
     *
     * Anybody else's screen gets the whole of STALE_MINUTES. This browser's own
     * cannot: a phone that pressed Present and came back to the remote would say
     * for five minutes that the show is on the phone in the cantor's hand. A
     * laptop with the wall in one window and the remote in the other is heard
     * from every SEEN_EVERY_SECONDS, and this is many of those — deliberately
     * many, because a browser throttles the timers of a window that is behind
     * another one, and a wall dropping out of its own remote's list would be a
     * worse mistake than one lingering in it.
     */
    public const PRESENTING_SECONDS = 90;

    /**
     * How far the picture on the wall may be pushed about.
     *
     * Wide enough for the square screen this exists for — a 1:1 deck on a 4:3
     * beamer hung high wants most of a quarter-picture of downward travel — and
     * narrow enough that a fumbled press cannot lose the deck off the edge of
     * the room, which on a laptop across the building is not something anybody
     * can walk over and undo.
     */
    public const FIT_MIN_SCALE = 0.25;

    public const FIT_MAX_SCALE = 2.0;

    public const FIT_MAX_OFFSET = 1.0;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'device_pairing_id',
        'session_id',
        'user_agent',
        'fit_scale',
        'fit_x',
        'fit_y',
        'applied_presentation_id',
        'applied_version',
        'drawn_revision',
        'applied_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'applied_at' => 'datetime',
            'applied_version' => 'integer',
            'fit_scale' => 'float',
            'fit_x' => 'float',
            'fit_y' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appliedPresentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class, 'applied_presentation_id');
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
     * What this screen's owner calls this device, where they have called it
     * anything.
     *
     * The name belongs to a person *and* a device, and only half of that pair is
     * expressible as a foreign key. The other half has to be constrained by
     * whoever loads it — `withDeviceName()` is that, and is what every query
     * reaching a label uses. Left alone, this relation would hand back whichever
     * row the device had, including the name a different cantor gave the same
     * parish laptop.
     */
    public function deviceName(): HasOne
    {
        return $this->hasOne(DeviceName::class, 'device_id', 'device_id');
    }

    /**
     * Load the labels, as the person asking for them.
     *
     * @param  Builder<Screen>  $query
     */
    public function scopeWithDeviceName(Builder $query, ?User $user = null): void
    {
        $userId = $user instanceof User ? $user->getKey() : Auth::id();

        $query->with(['deviceName' => function ($name) use ($userId): void {
            $name->where('user_id', $userId);
        }]);
    }

    /**
     * What to call this screen in a list of them.
     *
     * The typed name where there is one, and otherwise the crude user-agent
     * description that has always answered here. Naming is an override and never
     * a step: most screens are never named, and a nameless one must read exactly
     * as it did before any of this existed.
     */
    public function label(): string
    {
        // Loaded by `withDeviceName()`, which constrains it to the right person.
        // Asked of a screen nobody loaded it for — the presenter has only ever
        // its own — the constraint has to be applied here instead, or a laptop
        // two cantors share would show one of them the name the other gave it.
        $device = $this->relationLoaded('deviceName')
            ? $this->deviceName
            : DeviceName::query()
                ->where('user_id', $this->user_id)
                ->where('device_id', $this->device_id)
                ->first();

        $name = $device?->name;

        return is_string($name) && $name !== '' ? $name : $this->describeDevice();
    }

    /**
     * This browser, as the screen it is — claimed on the way into the page only
     * a wall opens.
     *
     * Keyed by the device, which keeps everything the session key was right
     * about and fixes the one thing it was not. Reloading is the same screen,
     * and so, deliberately, is a second window of the same browser, because the
     * cookie travels with both and it is the same room either way. What changes
     * is that the key now outlives a session: the parish laptop coming back next
     * Sunday is the row it was, rather than a new one beside an orphan.
     *
     * The session is still written, because the heartbeat still asks which
     * browser is speaking, but it is no longer what the row is found by.
     */
    public static function claimFor(
        User $user,
        string $deviceId,
        string $sessionId,
        ?int $devicePairingId = null,
        ?string $userAgent = null,
    ): self {
        $screen = self::query()->firstOrNew(['device_id' => $deviceId]);

        $screen->forceFill([
            'user_id' => $user->getKey(),
            'session_id' => $sessionId,
            'device_pairing_id' => $devicePairingId,
            'user_agent' => $userAgent,
            'last_seen_at' => Carbon::now(),
        ])->save();

        return $screen;
    }

    /**
     * Where this screen's picture lands, as its two clients speak of it.
     *
     * @return array{scale: float, x: float, y: float}
     */
    public function fit(): array
    {
        return [
            'scale' => (float) $this->fit_scale,
            'x' => (float) $this->fit_x,
            'y' => (float) $this->fit_y,
        ];
    }

    /**
     * Line the picture up on the wall, as the phone has just nudged it.
     *
     * Clamped here rather than trusted, because the phone is the thing across
     * the building from the projector: the person pressing an arrow cannot see
     * that the tenth press did nothing, and a screen that has been driven past
     * the edge of itself is a screen somebody has to walk to the laptop to
     * rescue. Nothing else about the screen moves — this is not the deck, and
     * changing it must not look to anyone like the service went anywhere.
     *
     * @param  array{scale?: float|int|string|null, x?: float|int|string|null, y?: float|int|string|null}  $fit
     */
    public function adjustFit(array $fit): void
    {
        $this->forceFill([
            'fit_scale' => self::clamp($fit['scale'] ?? $this->fit_scale, self::FIT_MIN_SCALE, self::FIT_MAX_SCALE),
            'fit_x' => self::clamp($fit['x'] ?? $this->fit_x, -self::FIT_MAX_OFFSET, self::FIT_MAX_OFFSET),
            'fit_y' => self::clamp($fit['y'] ?? $this->fit_y, -self::FIT_MAX_OFFSET, self::FIT_MAX_OFFSET),
        ])->save();
    }

    private static function clamp(float|int|string|null $value, float $low, float $high): float
    {
        return min($high, max($low, (float) $value));
    }

    /**
     * Note that the screen's own browser is still there.
     *
     * Its own browser and no other: the read that carries this is made by the
     * phone too, and a phone polling a laptop that has been closed must not keep
     * the laptop looking alive. That is the whole difference between a heartbeat
     * and a page view.
     *
     * Written at most once every SEEN_EVERY_SECONDS, because the caller is the
     * poll: left alone this is a row update every second for every wall in the
     * country, to move a column that is read against a five minute window. The
     * skipped write changes nothing anybody can observe — the row it would have
     * written is already well inside that window — and it is the difference
     * between a hundred writes a second and three.
     */
    public function touchLastSeen(): void
    {
        if ($this->last_seen_at?->gt(Carbon::now()->subSeconds(self::SEEN_EVERY_SECONDS))) {
            return;
        }

        $now = Carbon::now();
        $wasPresenting = $this->last_seen_at?->gt(Carbon::now()->subSeconds(self::PRESENTING_SECONDS)) ?? false;

        $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update(['last_seen_at' => $now]);

        $this->setAttribute('last_seen_at', $now)->syncOriginalAttribute('last_seen_at');

        // Written past the model, so no observer hears it — but a wall coming
        // back after a sleep is news to the remote, which lists it again.
        if (! $wasPresenting) {
            app(ShowStream::class)->changedFor($this->user_id);
        }
    }

    /**
     * Record the immutable show snapshot this browser applied to its DOM.
     *
     * An event and not a heartbeat. This used to be sent every ten seconds
     * whether anything had been drawn or not, which put a locked write and a
     * log line behind every wall in the country for as long as anybody was
     * singing — to say, nearly always, that nothing had happened. The wall now
     * sends it when what it has drawn stops matching what the show says it has
     * drawn, so a quiet Mass costs nothing at all, and the liveness the
     * heartbeat also carried is left to the poll that was making it anyway.
     *
     * @see Screen::touchLastSeen()
     */
    public function acknowledge(Presentation $presentation, int $version, ?string $drawnRevision): self
    {
        return DB::transaction(function () use ($presentation, $version, $drawnRevision): self {
            $screen = self::query()->lockForUpdate()->findOrFail($this->getKey());
            $isNewerPresentation = $screen->applied_presentation_id !== $presentation->getKey();
            $isNewerVersion = $version > $screen->applied_version;
            $isNewerRevision = $version === $screen->applied_version
                && $drawnRevision !== null
                && strcmp($drawnRevision, (string) $screen->drawn_revision) > 0;
            $now = Carbon::now();
            $attributes = [
                'last_seen_at' => $now,
                'applied_at' => $now,
            ];

            if ($isNewerPresentation || $isNewerVersion || $isNewerRevision) {
                $attributes += [
                    'applied_presentation_id' => $presentation->getKey(),
                    'applied_version' => $version,
                    'drawn_revision' => $drawnRevision,
                ];
            }

            $screen->forceFill($attributes)->save();

            return $screen;
        });
    }

    public function isLive(): bool
    {
        // A screen with no pairing was signed in with a password, and the null
        // short-circuit is the right answer for it: nothing was revoked.
        return $this->last_seen_at->gt(Carbon::now()->subMinutes(self::STALE_MINUTES))
            && $this->devicePairing?->revoked_at === null;
    }

    /**
     * Whether this screen's own browser is actually showing the show right now,
     * as against merely not having aged out yet.
     *
     * Only ever asked of the device doing the asking. Anybody else's screen is
     * shown for the whole of STALE_MINUTES, because a phone cannot walk across
     * the church to check; this browser's own cannot be, because a phone that
     * pressed Present and came back would otherwise say for five minutes that
     * the show is on the phone in the cantor's hand.
     */
    public function isPresenting(): bool
    {
        return $this->last_seen_at?->gt(Carbon::now()->subSeconds(self::PRESENTING_SECONDS)) === true;
    }

    /**
     * Whether this screen has said anything lately.
     *
     * What the remote draws its "screen not responding" from. A wall says this
     * by polling the show, which is the one thing a wall does whatever else is
     * or is not happening — so unlike everything else about a screen, it is
     * true of a wall sitting quietly through a long hymn.
     */
    public function isResponding(): bool
    {
        return $this->last_seen_at?->gt(Carbon::now()->subSeconds(self::SILENT_SECONDS)) === true;
    }

    /**
     * Whether its owner is willing to be offered this device as a screen.
     *
     * Read off the name row already loaded rather than asked as a subquery,
     * because the answer travels to the phone now: the show is described once
     * per person and the same description reaches every device, so which
     * screens a given device may see is decided where it is drawn.
     *
     * The absence of a row is the yes, so a device nobody has ever thought
     * about behaves exactly as it did before there was anything to think about.
     */
    public function isOffered(): bool
    {
        return $this->deviceName?->offered ?? true;
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

    /**
     * The screens their owner is willing to be offered — which is all of them
     * until somebody says otherwise.
     *
     * The absence of a row is the yes, so a device nobody has ever thought about
     * behaves exactly as it did before there was anything to think about.
     *
     * @param  Builder<Screen>  $query
     */
    public function scopeOffered(Builder $query, ?User $user = null): void
    {
        $userId = $user instanceof User ? $user->getKey() : Auth::id();

        $query->whereDoesntHave('deviceName', function (Builder $name) use ($userId): void {
            $name->where('user_id', $userId)->where('offered', false);
        });
    }
}
