<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $collection
 * @property string $piece
 * @property string $reference
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int $batch_number
 * @property int|null $page_number
 * @property string|null $tag
 * @property string|null $related
 * @property string|null $subtitle
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Music> $musics
 * @property-read int|null $musics_count
 *
 * @method static \Database\Factories\BulkImportFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereCollection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport wherePageNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport wherePiece($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereSubtitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BulkImport whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BulkImport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'collection',
        'piece',
        'reference',
        'page_number',
        'tag',
        'batch_number',
        'subtitle',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'batch_number' => 1,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference' => 'string',
            'page_number' => 'integer',
            'tag' => 'string',
            'batch_number' => 'integer',
            'subtitle' => 'string',
        ];
    }

    /**
     * Get the music pieces imported from this batch.
     */
    public function musics(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Music::class, 'import_batch_number', 'batch_number');
    }
}
