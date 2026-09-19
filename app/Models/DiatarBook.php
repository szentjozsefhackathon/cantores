<?php

namespace App\Models;

use Database\Factories\DiatarBookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiatarBook extends Model
{
    /** @use HasFactory<DiatarBookFactory> */
    use HasFactory;

    protected $fillable = [
        'source_path',
        'title',
        'short_name',
        'group',
        'source_revision',
        'checksum',
        'available',
        'unavailable_reason',
        'last_seen_sync_run_id',
        'source_order',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'boolean',
            'source_order' => 'integer',
        ];
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(DiatarSyncRun::class, 'last_seen_sync_run_id');
    }

    public function songs(): HasMany
    {
        return $this->hasMany(DiatarSong::class)->orderBy('source_order');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_diatar_book')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('available', true);
    }
}
