<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotImport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_import_id',
        'name',
        'column_number',
        'music_plan_slot_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_number' => 'integer',
        ];
    }

    /**
     * Get the music plan import that owns this slot import.
     */
    public function musicPlanImport(): BelongsTo
    {
        return $this->belongsTo(MusicPlanImport::class);
    }

    /**
     * Get the music plan slot that this import references (if found).
     */
    public function musicPlanSlot(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlot::class);
    }

    /**
     * Get the music imports for this slot.
     */
    public function musicImports(): HasMany
    {
        return $this->hasMany(MusicImport::class);
    }
}
