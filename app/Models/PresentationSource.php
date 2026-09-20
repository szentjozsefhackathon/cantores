<?php

namespace App\Models;

use Database\Factories\PresentationSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The last command accepted from one browser tab driving a presentation.
 *
 * @property int $id
 * @property int $presentation_id
 * @property string $source_id
 * @property int $last_sequence
 * @property int $applied_version
 * @property-read Presentation $presentation
 *
 * @method static PresentationSourceFactory factory($count = null, $state = [])
 */
class PresentationSource extends Model
{
    /** @use HasFactory<PresentationSourceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'presentation_id',
        'source_id',
        'last_sequence',
        'applied_version',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
            'applied_version' => 'integer',
        ];
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }
}
