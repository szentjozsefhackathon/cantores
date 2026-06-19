<?php

namespace App\Models;

use App\ScriptureReferenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $music_id
 * @property int|null $user_id
 * @property ScriptureReferenceType $reference_type
 * @property string $reference
 * @property string $text
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \OwenIt\Auditing\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Music $music
 * @property-read \App\Models\User|null $user
 *
 * @method static \Database\Factories\MusicScriptureReferenceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicScriptureReference newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicScriptureReference newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicScriptureReference query()
 *
 * @mixin \Eloquent
 */
class MusicScriptureReference extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_id',
        'user_id',
        'reference_type',
        'reference',
        'text',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_type' => ScriptureReferenceType::class,
        ];
    }

    /**
     * Get the music that owns this scripture reference.
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    /**
     * Get the user who added this scripture reference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
