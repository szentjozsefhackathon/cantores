<?php

namespace App\Models;

use App\Enums\MusicTagType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MusicTag extends Model
{
    /** @use HasFactory<\Database\Factories\MusicTagFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'type'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MusicTagType::class,
        ];
    }

    /**
     * Get the music pieces that have this tag.
     */
    public function music(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_music_tag');
    }

    /**
     * Get the display label for this tag.
     */
    public function label(): string
    {
        return $this->name;
    }

    /**
     * Get the icon for this tag's type.
     */
    public function icon(): string
    {
        return $this->type->icon();
    }

    /**
     * Get the color for this tag's type.
     */
    public function color(): string
    {
        return $this->type->color();
    }

    /**
     * Get the type label for this tag.
     */
    public function typeLabel(): string
    {
        return $this->type->label();
    }
}
