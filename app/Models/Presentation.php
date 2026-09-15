<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PresentationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * One deck being shown, on one screen, once.
 *
 * A Projection is what was arranged; a Presentation is that deck in front of a
 * congregation, and it exists for one reason: the person who knows when to
 * advance is at the organ, and the laptop driving the projector is somewhere
 * else in the building. Both devices are already signed in as the same person,
 * so all that was missing was a place for them to agree on where the service has
 * got to. This row is that place, and both of them are its clients.
 *
 * What it holds is an *address* — a row of the deck and a position within that
 * row — never an offset into an array. The presenter walks a filtered deck, and
 * the filter is the verses left out today; bring one back mid-service and every
 * offset after it moves. A row and a place within it survive that, and survive
 * an edit to the deck besides.
 *
 * @property int $id
 * @property int $projection_id
 * @property int $user_id
 * @property int|null $device_pairing_id
 * @property int|null $entry_id
 * @property int $slide_index
 * @property int|null $entry_sequence
 * @property bool $blanked
 * @property string $splash
 * @property int $version
 * @property string|null $drawn_revision
 * @property array<int|string, list<int>>|null $reveals
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Projection $projection
 * @property-read User $user
 * @property-read DevicePairing|null $devicePairing
 *
 * @method static \Database\Factories\PresentationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presentation live()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presentation mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presentation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presentation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Presentation query()
 *
 * @mixin \Eloquent
 */
class Presentation extends Model
{
    /** @use HasFactory<PresentationFactory> */
    use HasFactory;

    /**
     * How long a presentation nobody has heard from is still counted as live.
     *
     * Generous next to the poll that keeps it alive, because the cost of the two
     * mistakes is not symmetrical: a stale row shown in a list is an extra line
     * to read past, and a live row aged out by a moment of bad signal is a
     * remote that says the service has ended while it is going on.
     */
    public const STALE_MINUTES = 5;

    /**
     * A slide index no deck reaches, meaning "as far as this row goes".
     *
     * The server resolves which row the service is on, because only it knows
     * what the deck now contains; how many slides that row comes to is read off
     * the score in the browser every time it is drawn, so only the client can
     * clamp. This is how the one tells the other that the address ran off the
     * end of the deck.
     */
    public const LAST_SLIDE = 2147483647;

    /**
     * The three pictures a service opens with, in the order it walks through
     * them.
     *
     * The card is up while the projector window is dragged onto the beamer and
     * lined up against it. Then the room fills, and what belongs on the wall is
     * nothing — a title card held for twenty minutes in front of a seated
     * congregation is an advertisement and not a welcome. Then the first hymn is
     * announced and the deck begins.
     *
     * Each press moves it on one, which is also how a cantor gets out of the
     * card at all: with two states that was a thing you had to already know.
     */
    public const SPLASH_CARD = 'card';

    public const SPLASH_DARK = 'dark';

    public const SPLASH_OFF = 'off';

    /**
     * How far along that walk each picture is.
     *
     * A latch and not a switch: the opening is only ever walked forwards, and a
     * client reporting a picture earlier than the one the row has reached is
     * ignored. The wall reports every ten seconds whether anything happened, and
     * a heartbeat sent a moment before the phone's press lands a moment after
     * it — which without this would put the card back over the hymn the room had
     * just been given.
     *
     * @var array<string, int>
     */
    public const SPLASH_ORDER = [
        self::SPLASH_CARD => 0,
        self::SPLASH_DARK => 1,
        self::SPLASH_OFF => 2,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'projection_id',
        'user_id',
        'device_pairing_id',
        'entry_id',
        'slide_index',
        'entry_sequence',
        'blanked',
        'splash',
        'version',
        'drawn_revision',
        'reveals',
        'started_at',
        'last_seen_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blanked' => 'boolean',
            'reveals' => 'array',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function projection(): BelongsTo
    {
        return $this->belongsTo(Projection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Which borrowed screen is showing the deck, where one was signed in from a
     * phone. Nothing here depends on it.
     */
    public function devicePairing(): BelongsTo
    {
        return $this->belongsTo(DevicePairing::class);
    }

    /**
     * The presentation a presenter opening this deck should join, or a new one.
     *
     * Joining rather than starting afresh is what makes two windows on one deck
     * follow each other, which is the whole mechanism this feature is built out
     * of — the remote is only a third client of the same row.
     *
     * `$splash` asks for the opening — the title card, then the dark, then the
     * deck — and is asked for only by a presenter window opening a deck itself.
     * It reaches the row only when one is created: a second window joining a
     * service already under way must not put a card over the hymn the room is
     * singing.
     */
    public static function resumeFor(
        Projection $projection,
        User $user,
        ?int $devicePairingId = null,
        string $splash = self::SPLASH_OFF,
    ): self {
        $existing = self::query()
            ->live()
            ->where('projection_id', $projection->getKey())
            ->where('user_id', $user->getKey())
            ->latest('last_seen_at')
            ->first();

        if ($existing instanceof self) {
            $existing->touchLastSeen();

            return $existing;
        }

        $now = Carbon::now();

        return self::query()->create([
            'projection_id' => $projection->getKey(),
            'user_id' => $user->getKey(),
            'device_pairing_id' => $devicePairingId,
            'entry_id' => null,
            'slide_index' => 0,
            'blanked' => false,
            'splash' => $splash,

            'version' => 1,
            'started_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * Where the service is, resolved against the deck as it now stands.
     *
     * Forgiving on purpose: an edit made during the rehearsal is exactly when
     * this is asked, and losing the place because a row was reordered would be
     * worse than landing a slide out. If the row is still there the address is
     * returned untouched; if it is gone the service moves to the first slide of
     * the next row that survived, and to the end of the deck if there is none.
     *
     * @param  EloquentCollection<int, ProjectionSlide>  $entries  in deck order
     * @return array{entryId: int|null, slideIndex: int}
     */
    public function addressIn(EloquentCollection $entries): array
    {
        if ($entries->isEmpty()) {
            return ['entryId' => null, 'slideIndex' => 0];
        }

        $found = $entries->firstWhere('id', $this->entry_id);

        if ($found instanceof ProjectionSlide) {
            return ['entryId' => $found->id, 'slideIndex' => $this->slide_index];
        }

        // Nothing has been shown yet: the beginning.
        if ($this->entry_id === null) {
            return ['entryId' => $entries->first()->id, 'slideIndex' => 0];
        }

        $next = $entries->first(
            fn (ProjectionSlide $entry): bool => $entry->sequence >= ($this->entry_sequence ?? 0)
        );

        if ($next instanceof ProjectionSlide) {
            return ['entryId' => $next->id, 'slideIndex' => 0];
        }

        return ['entryId' => $entries->last()->id, 'slideIndex' => self::LAST_SLIDE];
    }

    /**
     * Today's revealed verses, minus any keyed to a row that has since gone.
     *
     * A reveal is a deviation from an arrangement, so it means nothing once the
     * thing it deviates from is no longer in the deck.
     *
     * @param  EloquentCollection<int, ProjectionSlide>  $entries
     * @return array<int, list<int>>
     */
    public function revealsIn(EloquentCollection $entries): array
    {
        $alive = $entries->pluck('id')->all();

        return collect($this->reveals ?? [])
            ->mapWithKeys(fn ($slides, $entryId): array => [(int) $entryId => array_values(array_map(
                intval(...),
                array_filter(is_array($slides) ? $slides : [], is_numeric(...)),
            ))])
            ->filter(fn (array $slides, int $entryId): bool => $slides !== [] && in_array($entryId, $alive, true))
            ->all();
    }

    /**
     * Write where the service has got to, and order the write against the reads
     * chasing it.
     *
     * The version moves when the state does and stands still otherwise. A
     * heartbeat carries the same address the presenter is already on, and
     * bumping the version for it would tell the remote that something happened
     * — which, a beat after the remote moved itself optimistically, reads as the
     * wall contradicting the tap that has not landed yet.
     *
     * The opening is a latch rather than a field: it is walked forwards and
     * never back. Every heartbeat carries it, and a request that says nothing
     * about it leaves it where it is — so a client reporting a picture the row
     * has already walked past changes nothing, and one reporting a later picture
     * moves every device on at once. Without that, the wall's ten-second
     * heartbeat would put the card back over a slide the phone had just moved
     * to.
     *
     * @param  array{entryId?: int|null, slideIndex?: int, blanked?: bool, splash?: string, reveals?: array<int, list<int>>}  $state
     */
    public function applyState(array $state, ?ProjectionSlide $entry = null): void
    {
        $next = [
            'entry_id' => array_key_exists('entryId', $state) ? $state['entryId'] : $this->entry_id,
            'slide_index' => array_key_exists('slideIndex', $state) ? max(0, (int) $state['slideIndex']) : $this->slide_index,
            'blanked' => array_key_exists('blanked', $state) ? (bool) $state['blanked'] : $this->blanked,
            'splash' => self::laterSplash($this->splash, $state['splash'] ?? null),
            'reveals' => array_key_exists('reveals', $state) ? ($state['reveals'] ?: null) : $this->reveals,
        ];

        $moved = $next['entry_id'] !== $this->entry_id
            || $next['slide_index'] !== $this->slide_index
            || $next['blanked'] !== $this->blanked
            || $next['splash'] !== $this->splash
            || self::canonicalReveals($next['reveals']) !== self::canonicalReveals($this->reveals);

        if ($entry instanceof ProjectionSlide && $entry->id === $next['entry_id']) {
            $next['entry_sequence'] = $entry->sequence;
        }

        $next['last_seen_at'] = Carbon::now();

        if ($moved) {
            $next['version'] = $this->version + 1;
        }

        $this->forceFill($next)->save();
    }

    /**
     * Whichever of two openings is the further along, the row's own winning any
     * tie and anything unrecognisable.
     */
    private static function laterSplash(string $current, ?string $reported): string
    {
        $here = self::SPLASH_ORDER[$current] ?? self::SPLASH_ORDER[self::SPLASH_OFF];
        $there = self::SPLASH_ORDER[$reported] ?? -1;

        return $there > $here ? $reported : $current;
    }

    /**
     * Today's reveals in one shape, so that two of them can be compared.
     *
     * They arrive from a browser as JSON and come back out of the column the
     * same way, and neither promises an order. Without this, a heartbeat
     * carrying the same reveals in a different order would count as a move and
     * tell every remote that something had happened.
     *
     * @param  array<int|string, list<int>>|null  $reveals
     * @return array<int, list<int>>
     */
    private static function canonicalReveals(?array $reveals): array
    {
        $canonical = [];

        foreach ($reveals ?? [] as $entryId => $slides) {
            $slides = array_values(array_unique(array_map(intval(...), (array) $slides)));
            sort($slides);

            if ($slides !== []) {
                $canonical[(int) $entryId] = $slides;
            }
        }

        ksort($canonical);

        return $canonical;
    }

    /**
     * Note that a client is still there, without pretending anything moved.
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

    /**
     * The service is over — said deliberately, rather than by falling silent.
     */
    public function end(): void
    {
        $this->forceFill(['ended_at' => Carbon::now()])->save();
    }

    public function isLive(): bool
    {
        return $this->ended_at === null
            && $this->last_seen_at->gt(Carbon::now()->subMinutes(self::STALE_MINUTES));
    }

    /**
     * @param  Builder<Presentation>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('ended_at')
            ->where('last_seen_at', '>', Carbon::now()->subMinutes(self::STALE_MINUTES));
    }

    /**
     * @param  Builder<Presentation>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
