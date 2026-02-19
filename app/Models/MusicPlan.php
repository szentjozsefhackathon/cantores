<?php

namespace App\Models;

use App\Concerns\HasVisibilityScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusicPlan extends Model
{
    use HasFactory;
    use HasVisibilityScoping;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'genre_id',
        'is_private',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the music plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the genre associated with this music plan.
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    /**
     * Get the celebrations for this music plan.
     */
    public function celebrations(): BelongsToMany
    {
        return $this->belongsToMany(Celebration::class, 'celebration_music_plan')
            ->withTimestamps();
    }

    /**
     * Get the liturgical celebrations (non-custom) for this music plan.
     */
    public function liturgicalCelebrations(): BelongsToMany
    {
        return $this->belongsToMany(Celebration::class, 'celebration_music_plan')
            ->where('is_custom', false)
            ->withTimestamps();
    }

    /**
     * Get the custom celebrations for this music plan.
     */
    public function customCelebrations(): BelongsToMany
    {
        return $this->belongsToMany(Celebration::class, 'celebration_music_plan')
            ->where('is_custom', true)
            ->withTimestamps();
    }

    /**
     * Get the slots for this music plan.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlanSlot::class, 'music_plan_slot_plan')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
    }

    /**
     * Get the music assignments for this plan.
     */
    public function musicAssignments(): HasMany
    {
        return $this->hasMany(MusicPlanSlotAssignment::class);
    }

    /**
     * Get the music items assigned to this plan (through assignments).
     * This is a convenience method that goes through the MusicPlanSlotAssignment model.
     */
    public function assignedMusic(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Music::class,
            MusicPlanSlotAssignment::class,
            'music_plan_id', // Foreign key on MusicPlanSlotAssignment table
            'id', // Foreign key on Music table
            'id', // Local key on MusicPlan table
            'music_id' // Foreign key on MusicPlanSlotAssignment table
        );
    }

    /**
     * Scope for plans by genre.
     */
    public function scopeByGenre($query, $genre)
    {
        if ($genre instanceof Genre) {
            return $query->where('genre_id', $genre->id);
        }

        return $query->where('genre_id', $genre);
    }

    /**
     * Scope for plans belonging to the current user's genre.
     */
    public function scopeForCurrentGenre($query)
    {
        $genreId = \App\Facades\GenreContext::getId();

        if ($genreId !== null) {
            // Show plans that belong to the current genre OR have no genre (belongs to all)
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }
        // If $genreId is null, no filtering applied (show all plans)
    }

    /**
     * Get the genre options for select inputs.
     */
    public static function genreOptions(): array
    {
        return Genre::options();
    }

    /**
     * Get the setting options with icons (backward compatibility).
     *
     * @deprecated Use genreOptions() instead
     */
    public static function settingOptions(): array
    {
        return [
            'organist' => __('Organist'),
            'guitarist' => __('Guitarist'),
            'other' => __('Other'),
        ];
    }

    /**
     * Get the liturgical year options.
     */
    public static function yearLetterOptions(): array
    {
        return [
            'A' => __('Year A'),
            'B' => __('Year B'),
            'C' => __('Year C'),
        ];
    }

    /**
     * Get the parity options.
     */
    public static function yearParityOptions(): array
    {
        return [
            'I' => __('I'),
            'II' => __('II'),
        ];
    }

    /**
     * Get the first celebration's day name for the liturgical day number.
     * This is a convenience method to access day name from the first associated celebration.
     */
    public function getDayNameAttribute(): string
    {
        $firstCelebration = $this->celebrations->first();
        if ($firstCelebration) {
            return $firstCelebration->day_name;
        }

        return '-';
    }

    /**
     * Get the first celebration's name.
     * This is a convenience method to access celebration name from the first associated celebration.
     */
    public function getCelebrationNameAttribute(): ?string
    {
        $firstCelebration = $this->celebrations->first();

        return $firstCelebration?->name;
    }

    /**
     * Get the first celebration's actual date.
     * This is a convenience method to access actual date from the first associated celebration.
     */
    public function getActualDateAttribute(): ?\Illuminate\Support\Carbon
    {
        $firstCelebration = $this->celebrations->first();

        $date = $firstCelebration?->actual_date;
        if ($date === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($date);
    }

    /**
     * Get the setting name from genre (backward compatibility).
     */
    public function getSettingAttribute(): ?string
    {
        return $this->genre?->name;
    }

    /**
     * Detach a slot from this music plan and delete all music assignments for that slot.
     */
    public function detachSlot(MusicPlanSlot|int $slot): void
    {
        $slotId = $slot instanceof MusicPlanSlot ? $slot->id : $slot;

        // Delete all music assignments for this slot in this plan
        $this->musicAssignments()
            ->where('music_plan_slot_id', $slotId)
            ->delete();

        // Detach the slot from the plan
        $this->slots()->detach($slotId);
    }

    /**
     * Detach all slots from this music plan and delete all music assignments.
     */
    public function detachAllSlots(): void
    {
        // Delete all music assignments for this plan
        $this->musicAssignments()->delete();

        // Detach all slots from the plan
        $this->slots()->detach();
    }

    /**
     * Create a custom celebration for this music plan.
     */
    public function createCustomCelebration(string $name, ?\Illuminate\Support\Carbon $date = null): Celebration
    {
        $date = $date ?? now();
        $dateString = $date->format('Y-m-d');

        // Find the next available celebration_key for this user and date
        $existingKeys = Celebration::where('is_custom', true)
            ->where('user_id', $this->user_id)
            ->where('actual_date', $dateString)
            ->pluck('celebration_key')
            ->toArray();

        $celebrationKey = 0;
        while (in_array($celebrationKey, $existingKeys)) {
            $celebrationKey++;
        }

        $celebration = Celebration::create([
            'name' => $name,
            'actual_date' => $dateString,
            'celebration_key' => $celebrationKey,
            'is_custom' => true,
            'user_id' => $this->user_id,
        ]);

        $this->celebrations()->attach($celebration);

        return $celebration;
    }

    /**
     * Check if this music plan has any custom celebrations.
     */
    public function hasCustomCelebrations(): bool
    {
        return $this->customCelebrations()->exists();
    }

    /**
     * Get the first custom celebration for this music plan.
     */
    public function firstCustomCelebration(): ?Celebration
    {
        return $this->customCelebrations()->first();
    }
}
