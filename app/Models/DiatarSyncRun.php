<?php

namespace App\Models;

use App\Enums\DiatarSyncStatus;
use Database\Factories\DiatarSyncRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiatarSyncRun extends Model
{
    /** @use HasFactory<DiatarSyncRunFactory> */
    use HasFactory;

    protected $fillable = [
        'source_revision',
        'status',
        'fetched_count',
        'indexed_count',
        'skipped_count',
        'unavailable_count',
        'warning_count',
        'started_at',
        'completed_at',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiatarSyncStatus::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'fetched_count' => 'integer',
            'indexed_count' => 'integer',
            'skipped_count' => 'integer',
            'unavailable_count' => 'integer',
            'warning_count' => 'integer',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(DiatarBook::class, 'last_seen_sync_run_id');
    }
}
