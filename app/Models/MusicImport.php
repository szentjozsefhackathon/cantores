<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicImport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_import_item_id',
        'slot_import_id',
        'music_id',
        'abbreviation',
        'label',
    ];

    /**
     * Get the music plan import item that owns this music import.
     */
    public function musicPlanImportItem(): BelongsTo
    {
        return $this->belongsTo(MusicPlanImportItem::class);
    }

    /**
     * Get the slot import that owns this music import.
     */
    public function slotImport(): BelongsTo
    {
        return $this->belongsTo(SlotImport::class);
    }

    /**
     * Get the music that this import references (if found).
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }
}
