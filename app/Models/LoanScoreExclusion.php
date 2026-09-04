<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score a folder or plan loan deliberately leaves out.
 *
 * The absence of rows is the common case and means everything is lent, so nothing
 * is written until a lender actually removes something.
 *
 * @property int $id
 * @property int $loan_id
 * @property int $score_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Loan $loan
 * @property-read \App\Models\Score $score
 *
 * @method static \Database\Factories\LoanScoreExclusionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanScoreExclusion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanScoreExclusion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoanScoreExclusion query()
 *
 * @mixin \Eloquent
 */
class LoanScoreExclusion extends Model
{
    /** @use HasFactory<\Database\Factories\LoanScoreExclusionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'loan_id',
        'score_id',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }
}
