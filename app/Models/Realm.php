<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Realm extends Model
{
    /** @use HasFactory<\Database\Factories\RealmFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * Get the music items associated with this realm.
     */
    public function music(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_realm');
    }

    /**
     * Get the collections associated with this realm.
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_realm');
    }

    /**
     * Get the users who have this realm as their current realm.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'current_realm_id');
    }

    /**
     * Get the music plans associated with this realm.
     */
    public function musicPlans()
    {
        return $this->hasMany(MusicPlan::class);
    }

    /**
     * Get the display label for this realm.
     */
    public function label(): string
    {
        return match ($this->name) {
            'organist' => __('Organist'),
            'guitarist' => __('Guitarist'),
            'other' => __('Other'),
            default => $this->name,
        };
    }

    /**
     * Get the icon name for this realm.
     */
    public function icon(): string
    {
        return match ($this->name) {
            'organist' => 'organist',
            'guitarist' => 'guitar',
            default => 'other',
        };
    }

    /**
     * Get the color for this realm.
     */
    public function color(): string
    {
        return match ($this->name) {
            'organist' => 'blue',
            'guitarist' => 'green',
            default => 'gray',
        };
    }

    /**
     * Get all realms as options for select inputs.
     */
    public static function options(): array
    {
        return self::all()->mapWithKeys(fn ($realm) => [
            $realm->id => $realm->label(),
        ])->toArray();
    }
}
