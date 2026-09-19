<?php

namespace App\Models;

use Database\Factories\DiatarMusicBindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiatarMusicBinding extends Model
{
    /** @use HasFactory<DiatarMusicBindingFactory> */
    use HasFactory;

    protected $fillable = [
        'music_id',
        'diatar_song_id',
        'is_active',
        'editor_note',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(DiatarSong::class, 'diatar_song_id');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(DiatarMusicBindingSlide::class)->orderBy('sequence');
    }
}
