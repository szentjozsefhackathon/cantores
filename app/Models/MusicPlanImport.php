<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicPlanImport extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source_file',
    ];

    /**
     * Get the import items for this batch.
     */
    public function importItems(): HasMany
    {
        return $this->hasMany(MusicPlanImportItem::class);
    }

    /**
     * Get the slot imports for this batch.
     */
    public function slotImports(): HasMany
    {
        return $this->hasMany(SlotImport::class);
    }
}
