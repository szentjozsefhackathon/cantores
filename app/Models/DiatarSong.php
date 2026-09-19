<?php

namespace App\Models;

use Database\Factories\DiatarSongFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiatarSong extends Model
{
    /** @use HasFactory<DiatarSongFactory> */
    use HasFactory;

    protected $fillable = [
        'diatar_book_id',
        'title',
        'reference',
        'source_order',
        'available',
        'diagnostic_reason',
        'last_seen_sync_run_id',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'boolean',
            'source_order' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(DiatarBook::class, 'diatar_book_id');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(DiatarSlide::class)->orderBy('source_order');
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(DiatarMusicBinding::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('available', true);
    }
}
