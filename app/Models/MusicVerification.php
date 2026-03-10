<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $music_id
 * @property int|null $verifier_id
 * @property string $field_name
 * @property int|null $pivot_reference
 * @property string $status
 * @property string|null $notes
 * @property \Carbon\CarbonImmutable|null $verified_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Music $music
 * @property-read \App\Models\User|null $verifier
 * @method static \Database\Factories\MusicVerificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification forField(string $fieldName)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification verified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereFieldName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification wherePivotReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicVerification whereVerifierId($value)
 * @mixin \Eloquent
 */
class MusicVerification extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'music_verifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_id',
        'verifier_id',
        'field_name',
        'pivot_reference',
        'status',
        'notes',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'pivot_reference' => 'integer',
        ];
    }

    /**
     * Get the music that this verification belongs to.
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    /**
     * Get the user (verifier) who performed the verification.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    /**
     * Scope for pending verifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for verified verifications.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Scope for rejected verifications.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for verifications for a specific field.
     */
    public function scopeForField($query, string $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    /**
     * Mark the verification as verified.
     */
    public function markAsVerified(?User $verifier = null, ?string $notes = null): void
    {
        $this->status = 'verified';
        $this->verified_at = now();
        if ($verifier) {
            $this->verifier_id = $verifier->id;
        }
        if ($notes !== null) {
            $this->notes = $notes;
        }
        $this->save();
    }

    /**
     * Mark the verification as rejected.
     */
    public function markAsRejected(?User $verifier = null, ?string $notes = null): void
    {
        $this->status = 'rejected';
        $this->verified_at = now();
        if ($verifier) {
            $this->verifier_id = $verifier->id;
        }
        if ($notes !== null) {
            $this->notes = $notes;
        }
        $this->save();
    }
}
