# Celebration Model Implementation Plan

## Overview
Create a Celebration model to allow music plans to be reused across multiple celebrations (e.g., same songs for December 8 each year). This involves:
1. Creating a Celebration model with celebration-related fields
2. Establishing a many-to-many relationship between MusicPlan and Celebration
3. Migrating existing data
4. Updating the createMusicPlan method to use Celebration model

## Database Schema Changes

### 1. Create celebrations table
```sql
CREATE TABLE celebrations (
    id BIGSERIAL PRIMARY KEY,
    celebration_key INTEGER NOT NULL,
    actual_date DATE NOT NULL,
    name VARCHAR(255) NOT NULL,
    season INTEGER NOT NULL,
    season_text VARCHAR(255) NULL,
    week INTEGER NOT NULL,
    day INTEGER NOT NULL,
    readings_code VARCHAR(255) NULL,
    year_letter CHAR(1) NULL,
    year_parity VARCHAR(255) NULL,
    created_at TIMESTAMP(0) NULL,
    updated_at TIMESTAMP(0) NULL,
    
    UNIQUE(actual_date, celebration_key)
);

CREATE INDEX celebrations_liturgical_lookup ON celebrations (season, week, day);
CREATE INDEX celebrations_actual_date_index ON celebrations (actual_date);
```

### 2. Create celebration_music_plan pivot table
```sql
CREATE TABLE celebration_music_plan (
    id BIGSERIAL PRIMARY KEY,
    celebration_id BIGINT NOT NULL REFERENCES celebrations(id) ON DELETE CASCADE,
    music_plan_id BIGINT NOT NULL REFERENCES music_plans(id) ON DELETE CASCADE,
    created_at TIMESTAMP(0) NULL,
    updated_at TIMESTAMP(0) NULL,
    
    UNIQUE(celebration_id, music_plan_id)
);

CREATE INDEX celebration_music_plan_celebration_id_index ON celebration_music_plan (celebration_id);
CREATE INDEX celebration_music_plan_music_plan_id_index ON celebration_music_plan (music_plan_id);
```

### 3. Remove celebration-related columns from music_plans table
Columns to remove (after data migration):
- celebration_name
- actual_date  
- season
- season_text
- week
- day
- readings_code
- year_letter
- year_parity

## Models

### Celebration Model (app/Models/Celebration.php)
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Celebration extends Model
{
    use HasFactory;

    protected $fillable = [
        'celebration_key',
        'actual_date',
        'name',
        'season',
        'season_text',
        'week',
        'day',
        'readings_code',
        'year_letter',
        'year_parity',
    ];

    protected function casts(): array
    {
        return [
            'actual_date' => 'date',
            'celebration_key' => 'integer',
            'season' => 'integer',
            'week' => 'integer',
            'day' => 'integer',
        ];
    }

    public function musicPlans(): BelongsToMany
    {
        return $this->belongsToMany(MusicPlan::class, 'celebration_music_plan')
            ->withTimestamps();
    }
}
```

### Update MusicPlan Model (app/Models/MusicPlan.php)
```php
// Add relationship
public function celebrations(): BelongsToMany
{
    return $this->belongsToMany(Celebration::class, 'celebration_music_plan')
        ->withTimestamps();
}

// Remove celebration-related fields from $fillable
// Update casts to remove celebration-related fields
// Keep only: user_id, setting, is_published
```

## Data Migration Strategy

### Phase 1: Add new tables, keep old columns
1. Create celebrations and celebration_music_plan tables
2. Keep existing columns in music_plans table

### Phase 2: Migrate existing data
For each MusicPlan record:
1. Find or create a Celebration record based on:
   - actual_date
   - celebration_name (maps to Celebration.name)
   - season, season_text, week, day, readings_code, year_letter, year_parity
   - celebration_key: Use NULL for existing records (or 0 if required)
2. Create entry in celebration_music_plan pivot table
3. Store the original celebration data in Celebration model

### Phase 3: Update application code
1. Update createMusicPlan method to use Celebration model
2. Update all other references to celebration fields in MusicPlan

### Phase 4: Remove old columns (optional)
After verifying everything works, remove celebration-related columns from music_plans table.

## Update createMusicPlan Method

Current implementation in liturgical-info.blade.php:
```php
$musicPlan = MusicPlan::create([
    'user_id' => $user->id,
    'celebration_name' => $celebration['name'] ?? $celebration['title'] ?? 'Unknown',
    'actual_date' => $celebration['dateISO'] ?? $this->date,
    // ... other celebration fields
    'is_published' => false,
]);
```

New implementation:
```php
// Find or create Celebration
$celebrationData = [
    'celebration_key' => $celebration['celebrationKey'] ?? 0,
    'actual_date' => $celebration['dateISO'] ?? $this->date,
    'name' => $celebration['name'] ?? $celebration['title'] ?? 'Unknown',
    'season' => (int) ($celebration['season'] ?? 0),
    'season_text' => $celebration['seasonText'] ?? null,
    'week' => (int) ($celebration['week'] ?? 0),
    'day' => (int) ($celebration['dayofWeek'] ?? 0),
    'readings_code' => $celebration['readingsId'] ?? null,
    'year_letter' => $celebration['yearLetter'] ?? null,
    'year_parity' => $celebration['yearParity'] ?? null,
];

$celebrationModel = Celebration::firstOrCreate(
    [
        'actual_date' => $celebrationData['actual_date'],
        'celebration_key' => $celebrationData['celebration_key'],
    ],
    $celebrationData
);

// Create MusicPlan without celebration fields
$musicPlan = MusicPlan::create([
    'user_id' => $user->id,
    'setting' => 'organist', // default
    'is_published' => false,
]);

// Attach celebration
$musicPlan->celebrations()->attach($celebrationModel->id);
```

## Other Code Updates Needed

1. **MusicPlans Livewire component** - Search functionality currently searches celebration_name, season_text, year_letter. Need to update to search through celebrations relationship.

2. **MusicPlan editor** - Currently displays celebration_name, actual_date, etc. Need to update to show data from attached celebrations.

3. **MusicPlan card component** - Displays celebration_name, actual_date. Need to update.

4. **Any other views or controllers** that reference celebration fields on MusicPlan.

## Testing Strategy

1. Create unit tests for Celebration model
2. Test many-to-many relationship
3. Test createMusicPlan method with Celebration
4. Test data migration
5. Update existing tests that use MusicPlan celebration fields

## Open Questions

1. What should celebration_key be for existing MusicPlan records that were created before celebration_key was stored?
2. Should we allow NULL celebration_key in the unique constraint?
3. Are there any other celebration fields from the API that should be stored for future use?
4. Should we add indexes for common queries (e.g., searching by name, date ranges)?

## Risks

1. Data loss if migration fails
2. Performance impact with joins for celebration data
3. Breaking existing functionality that expects celebration fields on MusicPlan
4. Complexity of updating all references to celebration fields

## Timeline
Implementation should be done in stages to minimize risk.