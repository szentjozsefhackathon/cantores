<?php

namespace App\Models;

use App\Enums\DirektoriumProcessingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $year
 * @property string $original_filename
 * @property string|null $source_url
 * @property string $file_path
 * @property int|null $total_pages
 * @property int $processed_pages
 * @property \App\Enums\DirektoriumProcessingStatus $processing_status
 * @property string|null $processing_error
 * @property bool $is_current
 * @property \Carbon\CarbonImmutable|null $processing_started_at
 * @property \Carbon\CarbonImmutable|null $processing_completed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DirektoriumEntry> $entries
 */
class DirektoriumEdition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'processing_status' => DirektoriumProcessingStatus::class,
            'is_current' => 'boolean',
            'processing_started_at' => 'immutable_datetime',
            'processing_completed_at' => 'immutable_datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DirektoriumEntry::class);
    }

    public function markAsCurrent(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_current' => false]);
        $this->update(['is_current' => true]);
    }

    public function processingProgressPercent(): int
    {
        if (! $this->total_pages || $this->total_pages === 0) {
            return 0;
        }

        return (int) round(($this->processed_pages / $this->total_pages) * 100);
    }
}
