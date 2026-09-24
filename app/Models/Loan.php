<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A lending link. One row is one deliberate loan of a Score, Folder or MusicPlan.
 *
 * A loan is not a gift: the score stays its owner's, the link may be passed along
 * a chain of people, and the owner can end it for everyone downstream at once.
 *
 * Access to a score reached *through* a folder or plan loan is derived at request
 * time by LoanAccessService rather than minted onto the score, so revoking this
 * single row revokes every URL underneath it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lendable_id
 * @property string $lendable_type
 * @property string $token
 * @property string|null $label
 * @property bool $allow_download
 * @property bool $restricted
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property int $open_count
 * @property CarbonImmutable|null $last_viewed_at
 * @property CarbonImmutable|null $contents_reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model|\Eloquent $lendable
 * @property-read User $user
 * @property-read Collection<int, User> $recipients
 *
 * @method static \Database\Factories\LoanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan live()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan openTo(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan unrestricted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan query()
 *
 * @mixin \Eloquent
 */
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    public const TOKEN_LENGTH = 32;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'restricted' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'lendable_id',
        'lendable_type',
        'token',
        'label',
        'allow_download',
        'restricted',
        'expires_at',
        'revoked_at',
        'last_viewed_at',
        'contents_reviewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_download' => 'boolean',
            'restricted' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'contents_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lendable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Every record of someone opening or keeping this loan.
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(ReceivedLoan::class);
    }

    /**
     * The scores this loan deliberately leaves out. Empty means everything.
     */
    public function exclusions(): HasMany
    {
        return $this->hasMany(LoanScoreExclusion::class);
    }

    /**
     * The people a restricted loan opens for, besides the lender.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'loan_recipients')->withTimestamps();
    }

    /**
     * Whether this loan lets the given reader in.
     *
     * An open loan admits anyone holding the link, signed in or not. A restricted
     * one admits only the lender and the people named on it, and never a guest.
     */
    public function admits(?User $user): bool
    {
        if (! $this->restricted) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $user->getKey() === $this->user_id
            || $this->recipients()->whereKey($user->getKey())->exists();
    }

    /**
     * Whether this loan opens a container — a folder or plan whose contents can be
     * excluded one by one — as opposed to a single score.
     */
    public function isContainer(): bool
    {
        return ! $this->lendable instanceof Score;
    }

    /**
     * The address this loan is followed at.
     */
    public function url(): string
    {
        return match (true) {
            $this->lendable instanceof Folder => route('folder.loan', ['token' => $this->token]),
            $this->lendable instanceof MusicPlan => route('music-plan.loan', ['token' => $this->token]),
            $this->lendable instanceof Booklet => route('booklet.loan', ['token' => $this->token]),
            default => route('score.loan', ['token' => $this->token]),
        };
    }

    /**
     * Generate a token that no existing share uses.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function revoke(): void
    {
        $this->revoked_at = Carbon::now();
        $this->save();
    }

    /**
     * Record that the link was followed, without touching `updated_at`.
     *
     * Counted for everyone, named for nobody: who opened it is recorded in
     * `received_loans` and only when they are signed in.
     */
    public function touchLastViewed(): void
    {
        $this->getConnection()
            ->table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->incrementEach(['open_count' => 1], ['last_viewed_at' => Carbon::now()]);
    }

    /**
     * Scope to loans that are neither revoked nor expired.
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            });
    }

    /**
     * Scope to loans the given user may open: open ones, their own, and restricted
     * ones that name them.
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeOpenTo(Builder $query, int $userId): void
    {
        $query->where(function (Builder $query) use ($userId): void {
            $query->where('restricted', false)
                ->orWhere('user_id', $userId)
                ->orWhereHas('recipients', fn (Builder $recipients) => $recipients->whereKey($userId));
        });
    }

    /**
     * Scope to loans that are not restricted to named people — the only ones whose
     * scores may travel on in a borrower's own folder, plan or booklet.
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeUnrestricted(Builder $query): void
    {
        $query->where('restricted', false);
    }

    /**
     * @param  Builder<Loan>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
