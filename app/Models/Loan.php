<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $revoked_at
 * @property int $open_count
 * @property \Carbon\CarbonImmutable|null $last_viewed_at
 * @property \Carbon\CarbonImmutable|null $contents_reviewed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Model|\Eloquent $lendable
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\LoanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan live()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Loan query()
 *
 * @mixin \Eloquent
 */
class Loan extends Model
{
    /** @use HasFactory<\Database\Factories\LoanFactory> */
    use HasFactory;

    public const TOKEN_LENGTH = 32;

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
     * Whether this loan opens a container — a folder or plan whose contents can be
     * excluded one by one — as opposed to a single score.
     */
    public function isContainer(): bool
    {
        return ! $this->lendable instanceof Score;
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
     * @param  Builder<Loan>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
