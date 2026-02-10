# MusicPlan Design Document

## Overview
MusicPlan represents a personal plan for a Catholic Mass. It is connected to a user and contains liturgical information to help associate MassPlans for historical lookup purposes.

## Database Schema

### Table: `music_plans`
| Column | Type | Description | Constraints |
|--------|------|-------------|-------------|
| `id` | bigint | Primary key | AUTO_INCREMENT |
| `user_id` | bigint | Foreign key to users table | NOT NULL, INDEX |
| `celebration_name` | string | Name for the feast (e.g., "6th Sunday Ordinary Time") | NOT NULL |
| `actual_date` | date | The actual date when the plan is used (e.g., 2026-02-15) | NOT NULL |
| `setting` | string | Enum: 'organist', 'guitarist', 'other' | NOT NULL, DEFAULT 'organist' |
| `season` | integer | Liturgical season identifier | NOT NULL |
| `week` | integer | Week of the season | NOT NULL |
| `day` | integer | Day on the week of the season | NOT NULL |
| `readings_code` | string | Code for the readings (used by external system) | NULLABLE |
| `year_letter` | char(1) | Liturgical year: A, B, or C | NULLABLE |
| `year_parity` | string | I/II parity for weekday masses | NULLABLE |
| `is_published` | boolean | Whether the plan is published or private | DEFAULT false |
| `created_at` | timestamp | When the record was created | NULLABLE |
| `updated_at` | timestamp | When the record was last updated | NULLABLE |

### Indexes
1. Primary key: `id`
2. Foreign key index: `user_id` (references `users.id`)
3. Lookup index: `(season, week, day, liturgical_year)` for efficient historical lookups
4. Date index: `actual_date` for date-based queries
5. User visibility index: `(user_id, is_published)` for user-specific queries

### Foreign Keys
- `music_plans.user_id` → `users.id` (cascade on delete)

## Eloquent Model Design

### Model: `MusicPlan`
Located at `app/Models/MusicPlan.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'celebration_name',
        'actual_date',
        'setting',
        'season',
        'week',
        'day',
        'readings_code',
        'yearLetter',
        'yearParity',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actual_date' => 'date',
            'is_published' => 'boolean',
            'season' => 'integer',
            'week' => 'integer',
            'day' => 'integer',
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
     * Scope for published plans.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope for private plans.
     */
    public function scopePrivate($query)
    {
        return $query->where('is_published', false);
    }

    /**
     * Scope for plans by setting.
     */
    public function scopeBySetting($query, string $setting)
    {
        return $query->where('setting', $setting);
    }

    /**
     * Get the setting options with icons.
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
}
```

## Enum Design

### Setting Enum
Since settings will have icons and require frequent filtering, we'll create a dedicated enum class:

```php
<?php

namespace App\Enums;

enum MusicPlanSetting: string
{
    case ORGANIST = 'organist';
    case GUITARIST = 'guitarist';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ORGANIST => __('Organist'),
            self::GUITARIST => __('Guitarist'),
            self::OTHER => __('Other'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORGANIST => 'music',
            self::GUITARIST => 'guitar',
            self::OTHER => 'settings',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ORGANIST => 'blue',
            self::GUITARIST => 'green',
            self::OTHER => 'gray',
        };
    }

    public static function options(): array
    {
        return [
            self::ORGANIST->value => self::ORGANIST->label(),
            self::GUITARIST->value => self::GUITARIST->label(),
            self::OTHER->value => self::OTHER->label(),
        ];
    }
}
```