<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record that this person met this loan.
 *
 * Two readings of one table. The borrower's list — *Kölcsönkapott kották* — reads
 * the rows they deliberately kept; the lender's reach figures read every row.
 *
 * It is **never** consulted for authorization. LoanAccessService is the only gate,
 * so a kept row whose loan has since been revoked is a dead bookmark and not a
 * back door.
 *
 * When a score is reached through a chain — Márta lends a plan to Béla, Béla lends
 * his own plan onward — keeping it records the *root* loan the score originates
 * from, scoped to that one score. An intermediary is a route, not a rights-holder:
 * Béla deleting his plan must not confiscate Márta's freely-lent score.
 *
 * @property int $id
 * @property int $user_id
 * @property int $loan_id
 * @property int|null $score_id
 * @property \Carbon\CarbonImmutable $first_opened_at
 * @property \Carbon\CarbonImmutable $last_opened_at
 * @property \Carbon\CarbonImmutable|null $kept_at
 * @property \Carbon\CarbonImmutable|null $hidden_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Loan $loan
 * @property-read \App\Models\Score|null $score
 *
 * @method static \Database\Factories\ReceivedLoanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedLoan kept()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedLoan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedLoan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedLoan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedLoan visible()
 *
 * @mixin \Eloquent
 */
class ReceivedLoan extends Model
{
    /** @use HasFactory<\Database\Factories\ReceivedLoanFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'loan_id',
        'score_id',
        'first_opened_at',
        'last_opened_at',
        'kept_at',
        'hidden_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'kept_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    public function isKept(): bool
    {
        return $this->kept_at !== null;
    }

    public function keep(): void
    {
        if ($this->kept_at === null) {
            $this->kept_at = Carbon::now();
        }

        $this->hidden_at = null;
        $this->save();
    }

    /**
     * Scope to rows the borrower deliberately saved.
     *
     * @param  Builder<ReceivedLoan>  $query
     */
    public function scopeKept(Builder $query): void
    {
        $query->whereNotNull('kept_at');
    }

    /**
     * Scope to rows the borrower has not dismissed.
     *
     * @param  Builder<ReceivedLoan>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }
}
