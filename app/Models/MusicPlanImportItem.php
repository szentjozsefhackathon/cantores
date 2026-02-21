<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicPlanImportItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_plan_import_id',
        'celebration_date',
        'celebration_info',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'celebration_date' => 'date',
        ];
    }

    /**
     * Get the music plan import that owns this item.
     */
    public function musicPlanImport(): BelongsTo
    {
        return $this->belongsTo(MusicPlanImport::class);
    }

    /**
     * Get the music imports for this item.
     */
    public function musicImports(): HasMany
    {
        return $this->hasMany(MusicImport::class);
    }
}
