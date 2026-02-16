<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'batch_number',
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
            'batch_number' => 'integer',
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
