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
        'private_notes',
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
     * Get the custom slots created specifically for this music plan.
     */
    public function customSlots(): HasMany
    {
        return $this->hasMany(MusicPlanSlot::class)
            ->where('is_custom', true);
    }

    /**
     * Get all slots available for this plan (both global and custom).
     * This returns a query builder that can be further filtered.
     */
    public function allSlots()
    {
        return MusicPlanSlot::forPlan($this);
    }

    /**
     * Create a custom slot for this music plan.
     */
    public function createCustomSlot(array $data): MusicPlanSlot
    {
        $data['music_plan_id'] = $this->id;
        $data['user_id'] = $this->user_id;
        $data['is_custom'] = true;

        // Ensure priority is 0 for custom slots if not specified
        if (! isset($data['priority'])) {
            $data['priority'] = 0;
        }

        return MusicPlanSlot::create($data);
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
    public function createCustomCelebration(string $name, ?\DateTimeInterface $date = null): Celebration
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

    /**
     * Create a copy of this music plan with all its slots and assignments.
     * When copying a published plan (not owned by the user), excludes custom slots,
     * private notes, and private music assignments.
     */
    public function copy(?User $copier = null): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($copier) {
            // Determine if this is a published plan being copied by a non-owner
            $isPublishedCopy = $copier && $copier->id !== $this->user_id && ! $this->is_private;

            // Create a new music plan with the same attributes
            $newPlan = self::create([
                'user_id' => $copier?->id ?? $this->user_id,
                'genre_id' => $this->genre_id,
                'is_private' => true, // New copies are always private
                'private_notes' => $isPublishedCopy ? null : $this->private_notes,
            ]);

            // Copy all celebrations (both custom and liturgical)
            foreach ($this->celebrations as $celebration) {
                $newPlan->celebrations()->attach($celebration);
            }

            // Get all slots with their sequence and pivot ID
            $slots = $this->slots()
                ->withPivot('sequence', 'id')
                ->orderBy('music_plan_slot_plan.sequence')
                ->get();

            // Copy each slot and its assignments
            foreach ($slots as $slot) {
                // Skip custom slots when copying a published plan
                if ($isPublishedCopy && $slot->is_custom) {
                    continue;
                }

                // For custom slots, create a new custom slot for the new plan
                if ($slot->is_custom) {
                    $newSlot = $newPlan->createCustomSlot([
                        'name' => $slot->name,
                        'description' => $slot->description,
                        'priority' => $slot->priority,
                    ]);
                    $slotId = $newSlot->id;
                } else {
                    // For global slots, just use the same slot
                    $slotId = $slot->id;
                }

                // Attach the slot to the new plan with the same sequence
                $newPlan->slots()->attach($slotId, [
                    'sequence' => $slot->pivot->sequence,
                ]);

                // Get the new pivot record ID directly from the database
                $newPivotId = \Illuminate\Support\Facades\DB::table('music_plan_slot_plan')
                    ->where('music_plan_id', $newPlan->id)
                    ->where('music_plan_slot_id', $slotId)
                    ->value('id');

                // Copy all assignments for this slot
                $assignments = MusicPlanSlotAssignment::where('music_plan_slot_plan_id', $slot->pivot->id)
                    ->orderBy('music_sequence')
                    ->with(['flags', 'scopes', 'music'])
                    ->get();

                foreach ($assignments as $assignment) {
                    // Skip private music when copying a published plan
                    if ($isPublishedCopy && $assignment->music && $assignment->music->is_private) {
                        continue;
                    }

                    $newAssignment = MusicPlanSlotAssignment::create([
                        'music_plan_slot_plan_id' => $newPivotId,
                        'music_plan_id' => $newPlan->id,
                        'music_plan_slot_id' => $slotId,
                        'music_id' => $assignment->music_id,
                        'music_sequence' => $assignment->music_sequence,
                        'notes' => $assignment->notes,
                    ]);

                    // Copy flags
                    if ($assignment->flags->isNotEmpty()) {
                        $newAssignment->flags()->sync($assignment->flags->pluck('id'));
                    }

                    // Copy scopes
                    foreach ($assignment->scopes as $scope) {
                        $newAssignment->scopes()->create([
                            'scope_type' => $scope->scope_type,
                            'scope_number' => $scope->scope_number,
                        ]);
                    }
                }
            }

            return $newPlan;
        });
    }
}
