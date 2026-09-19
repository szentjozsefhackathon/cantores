<?php

namespace App\Models;

use Database\Factories\DiatarMusicBindingSlideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiatarMusicBindingSlide extends Model
{
    /** @use HasFactory<DiatarMusicBindingSlideFactory> */
    use HasFactory;

    protected $fillable = [
        'diatar_music_binding_id',
        'diatar_slide_id',
        'sequence',
    ];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(DiatarMusicBinding::class, 'diatar_music_binding_id');
    }

    public function slide(): BelongsTo
    {
        return $this->belongsTo(DiatarSlide::class, 'diatar_slide_id');
    }
}
