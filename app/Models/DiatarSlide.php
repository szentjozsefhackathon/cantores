<?php

namespace App\Models;

use Database\Factories\DiatarSlideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiatarSlide extends Model
{
    /** @use HasFactory<DiatarSlideFactory> */
    use HasFactory;

    protected $fillable = [
        'diatar_song_id',
        'source_order',
        'external_id',
        'verse_name',
        'is_exportable',
        'diagnostic_reason',
        'last_seen_sync_run_id',
    ];

    protected function casts(): array
    {
        return [
            'source_order' => 'integer',
            'is_exportable' => 'boolean',
        ];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(DiatarSong::class, 'diatar_song_id');
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(DiatarSyncRun::class, 'last_seen_sync_run_id');
    }

    public function scopeExportable(Builder $query): void
    {
        $query->where('is_exportable', true)->whereNotNull('external_id');
    }
}
