<?php

namespace App\Models;

use App\Concerns\HasVisibilityScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $is_private
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $genre_id
 * @property string|null $private_notes
 * @property int|null $celebration_id
 * @property-read \App\Models\Celebration|null $celebration
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlot> $customSlots
 * @property-read int|null $custom_slots_count
 * @property-read \App\Models\Genre|null $genre
 * @property-read \Illuminate\Support\Carbon|null $actual_date
 * @property-read string|null $celebration_name
 * @property-read string $day_name
 * @property-read string|null $setting
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlotAssignment> $musicAssignments
 * @property-read int|null $music_assignments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicPlanSlot> $slots
 * @property-read int|null $slots_count
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan byGenre($genre)
 * @method static \Database\Factories\MusicPlanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan forCurrentGenre()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan private()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan public()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan visibleTo(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereCelebrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereGenreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereIsPrivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicPlan withVisibleRelation(string $relation, ?\App\Models\User $user = null)
 *
 * @mixin \Eloquent
 */
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
     * Get the celebration for this music plan.
     */
    public function celebration(): BelongsTo
    {
        return $this->belongsTo(Celebration::class);
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
     * Get the music assignments for this plan (via the slot plan pivot).
     */
    public function musicAssignments(): HasManyThrough
    {
        return $this->hasManyThrough(
            MusicPlanSlotAssignment::class,
            MusicPlanSlotPlan::class,
            'music_plan_id',           // FK on music_plan_slot_plan referencing music_plans.id
            'music_plan_slot_plan_id', // FK on music_plan_slot_assignments referencing music_plan_slot_plan.id
            'id',                      // local key on music_plans
            'id'                       // local key on music_plan_slot_plan
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
        if ($this->celebration) {
            return $this->celebration->day_name;
        }

        return '-';
    }

    /**
     * Get the first celebration's name.
     * This is a convenience method to access celebration name from the first associated celebration.
     */
    public function getCelebrationNameAttribute(): ?string
    {
        return $this->celebration?->name;
    }

    /**
     * Get the first celebration's actual date.
     * This is a convenience method to access actual date from the first associated celebration.
     */
    public function getActualDateAttribute(): ?\Illuminate\Support\Carbon
    {
        $date = $this->celebration?->actual_date;
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
     * Attach a slot to this plan at the sequence position determined by the slot's priority.
     * Walks the existing slots in their current sequence order and inserts the new slot
     * before the first slot whose priority is greater, leaving all other sequences intact.
     */
    public function attachSlotAtPriorityPosition(MusicPlanSlot|int $slot): MusicPlanSlotPlan
    {
        $slotId = $slot instanceof MusicPlanSlot ? $slot->id : $slot;
        $newSlot = $slot instanceof MusicPlanSlot ? $slot : MusicPlanSlot::findOrFail($slotId);

        $existingSlotPlans = MusicPlanSlotPlan::with('musicPlanSlot')
            ->whereHas('musicPlanSlot')
            ->where('music_plan_id', $this->id)
            ->orderBy('sequence')
            ->get();

        $insertAt = null;
        foreach ($existingSlotPlans as $slotPlan) {
            if ($slotPlan->musicPlanSlot->priority > $newSlot->priority) {
                $insertAt = $slotPlan->sequence;
                break;
            }
        }

        if ($insertAt !== null) {
            MusicPlanSlotPlan::where('music_plan_id', $this->id)
                ->where('sequence', '>=', $insertAt)
                ->increment('sequence');

            $this->slots()->attach($slotId, ['sequence' => $insertAt]);
        } else {
            $maxSequence = $existingSlotPlans->max('sequence') ?? 0;
            $this->slots()->attach($slotId, ['sequence' => $maxSequence + 1]);
        }

        return MusicPlanSlotPlan::where('music_plan_id', $this->id)
            ->where('music_plan_slot_id', $slotId)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * Detach a slot from this music plan and delete all music assignments for that slot.
     */
    public function detachSlot(MusicPlanSlot|int $slot): void
    {
        $slotId = $slot instanceof MusicPlanSlot ? $slot->id : $slot;

        // Detach the slot; the cascadeOnDelete FK on music_plan_slot_assignments deletes assignments
        $this->slots()->detach($slotId);
    }

    /**
     * Detach all slots from this music plan and delete all music assignments.
     */
    public function detachAllSlots(): void
    {
        // Detach all slots; the cascadeOnDelete FK on music_plan_slot_assignments deletes assignments
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

        $this->celebration()->associate($celebration);
        $this->save();

        return $celebration;
    }

    /**
     * Check if this music plan has any custom celebrations.
     */
    public function hasCustomCelebrations(): bool
    {
        return $this->celebration !== null && $this->celebration->is_custom;
    }

    /**
     * Get the first custom celebration for this music plan.
     */
    public function firstCustomCelebration(): ?Celebration
    {
        $celebration = $this->celebration;

        return ($celebration !== null && $celebration->is_custom) ? $celebration : null;
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

            // Copy celebration
            if ($this->celebration_id !== null) {
                $newPlan->celebration()->associate($this->celebration_id);
                $newPlan->save();
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
                    ->with(['scopes', 'music'])
                    ->get();

                foreach ($assignments as $assignment) {
                    // Skip private music when copying a published plan
                    if ($isPublishedCopy && $assignment->music && $assignment->music->is_private) {
                        continue;
                    }

                    $newAssignment = MusicPlanSlotAssignment::create([
                        'music_plan_slot_plan_id' => $newPivotId,
                        'music_id' => $assignment->music_id,
                        'music_sequence' => $assignment->music_sequence,
                        'notes' => $assignment->notes,
                        'music_assignment_flag_id' => $assignment->music_assignment_flag_id,
                    ]);

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
