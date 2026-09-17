<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PresentationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * One time a deck was put up.
 *
 * A Projection is what was arranged; a Presentation is that deck in front of a
 * congregation, and it exists for one reason: the person who knows when to
 * advance is at the organ, and the laptop driving the projector is somewhere
 * else in the building. Both devices are already signed in as the same person,
 * so all that was missing was a place for them to agree on where the service has
 * got to. This row is that place, and both of them are its clients.
 *
 * A person has at most one un-ended row, and that row is their show: every
 * device of theirs follows it, and a screen shows it because the screen is
 * theirs, not because it points anywhere. The ended rows stay behind, and are
 * what "recently shown" is read from.
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
     * Put a deck up as this person's show.
     *
     * A person has one show at a time, and every device of theirs follows it:
     * the wall, the phone, the laptop's second window. So putting a deck up is
     * not joining "this deck's presentation" but replacing whatever the show
     * was — ended in the same transaction, and a new row started at the
     * beginning of the deck.
     *
     * Unless the deck is already the show. Reloading the wall, pressing Present
     * twice, or picking the deck that is already on the phone must change
     * nothing, so that returns the row as it is, position and all.
     *
     * The title card comes up only when nothing was live: a deck replacing one
     * mid-service goes straight to the deck, because the room is already
     * looking at something and a card over it would read as the service
     * stopping rather than moving on.
     *
     * A blanked wall stays blanked. Decks are put up ahead of the moment they
     * are wanted, and only a press of B ever puts a picture back in front of
     * the room.
     *
     * Two of these racing — two devices pressing at once — are settled by the
     * partial unique index on the un-ended row. The loser retries once, and on
     * the retry it finds the winner's row and joins it or replaces it like any
     * other.
     */
    public static function putUp(User $user, Projection $projection): self
    {
        try {
            return self::putUpOnce($user, $projection);
        } catch (UniqueConstraintViolationException) {
            return self::putUpOnce($user, $projection);
        }
    }

    private static function putUpOnce(User $user, Projection $projection): self
    {
        return DB::transaction(function () use ($user, $projection): self {
            $current = self::query()
                ->mine($user)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            $live = $current instanceof self && $current->isLive();

            if ($live && $current->projection_id === $projection->getKey()) {
                $current->touchLastSeen();

                return $current;
            }

            $current?->end();

            $now = Carbon::now();

            return self::query()->create([
                'projection_id' => $projection->getKey(),
                'user_id' => $user->getKey(),
                'entry_id' => null,
                'slide_index' => 0,
                'blanked' => $live && $current->blanked,
                'splash' => $live ? self::SPLASH_OFF : self::SPLASH_CARD,
                'version' => 1,
                'started_at' => $now,
                'last_seen_at' => $now,
            ]);
        });
    }

    /**
     * This person's show, once one nobody has heard from for a while is counted
     * as nothing — so a deck left up on Saturday does not come back on Sunday.
     */
    public static function currentFor(User $user): ?self
    {
        return self::query()
            ->mine($user)
            ->live()
            ->first();
    }

    /**
     * Take the show down. The deliberate end of a service, from whichever
     * device says so.
     */
    public static function takeDownFor(User $user): void
    {
        self::query()
            ->mine($user)
            ->whereNull('ended_at')
            ->get()
            ->each(fn (self $presentation) => $presentation->end());
    }

    /**
     * The decks this person has put up lately, newest first and each once.
     *
     * Read off the presentation rows rather than stored: every deck put up
     * leaves one behind, so the adoration deck tried yesterday is one tap away
     * today without anything having been kept for the purpose.
     *
     * @return EloquentCollection<int, Projection>
     */
    public static function recentFor(User $user, int $limit = 3): EloquentCollection
    {
        return Projection::query()
            ->mine($user)
            ->whereHas('presentations', fn (Builder $presentations) => $presentations->mine($user))
            ->withMax(['presentations as last_shown_at' => fn (Builder $presentations) => $presentations->mine($user)], 'started_at')
            ->orderByDesc('last_shown_at')
            ->limit($limit)
            ->get();
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
     * Note that somebody is still following the show, at most once every
     * `Screen::SEEN_EVERY_SECONDS`.
     *
     * The caller is the poll, and the column is read against a five minute
     * window, so a write a second would be three hundred times more than
     * anything asks for.
     */
    public function keepAlive(): void
    {
        if ($this->last_seen_at->gt(Carbon::now()->subSeconds(Screen::SEEN_EVERY_SECONDS))) {
            return;
        }

        $this->touchLastSeen();
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
