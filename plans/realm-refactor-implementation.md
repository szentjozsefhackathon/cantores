# Realm Refactor Implementation Plan

## Overview
Replace the `MusicPlanSetting` enum with a `Realm` entity to allow many-to-many relationships between Realms, Music, and Collections. Users will have a nullable `current_realm_id` foreign key.

## Current State Analysis
- `MusicPlanSetting` is an enum with values: `organist`, `guitarist`, `other`
- Used in `MusicPlan` model's `setting` field (string)
- Displayed in UI via `MusicPlanSetting::tryFrom($setting)?->label()` and icon component
- No existing many-to-many relationships with Music or Collection

## New Architecture

### Database Schema Changes

#### 1. Create `realms` table
```php
Schema::create('realms', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique(); // 'organist', 'guitarist', 'other'
    $table->timestamps();
});
```

#### 2. Create pivot tables
```php
Schema::create('music_realm', function (Blueprint $table) {
    $table->id();
    $table->foreignId('music_id')->constrained()->cascadeOnDelete();
    $table->foreignId('realm_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    
    $table->unique(['music_id', 'realm_id']);
});

Schema::create('collection_realm', function (Blueprint $table) {
    $table->id();
    $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
    $table->foreignId('realm_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    
    $table->unique(['collection_id', 'realm_id']);
});
```

#### 3. Add `current_realm_id` to `users` table
```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('current_realm_id')->nullable()->constrained('realms')->nullOnDelete();
});
```

#### 4. Update `music_plans` table
Option A: Replace `setting` with `realm_id` (preferred if deleting existing data)
```php
Schema::table('music_plans', function (Blueprint $table) {
    $table->dropColumn('setting');
    $table->foreignId('realm_id')->nullable()->constrained()->nullOnDelete();
});
```

Option B: Keep `setting` as string for backward compatibility during transition, add `realm_id` separately

### Model Changes

#### 1. Create `App\Models\Realm` model
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Realm extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function music(): BelongsToMany
    {
        return $this->belongsToMany(Music::class, 'music_realm');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_realm');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'current_realm_id');
    }

    public function musicPlans()
    {
        return $this->hasMany(MusicPlan::class);
    }

    // Methods similar to MusicPlanSetting enum
    public function label(): string
    {
        return match ($this->name) {
            'organist' => __('Organist'),
            'guitarist' => __('Guitarist'),
            'other' => __('Other'),
            default => $this->name,
        };
    }

    public function icon(): string
    {
        return match ($this->name) {
            'organist' => 'organist',
            'guitarist' => 'guitar',
            default => 'other',
        };
    }

    public function color(): string
    {
        return match ($this->name) {
            'organist' => 'blue',
            'guitarist' => 'green',
            default => 'gray',
        };
    }

    public static function options(): array
    {
        return self::all()->mapWithKeys(fn ($realm) => [
            $realm->id => $realm->label(),
        ])->toArray();
    }
}
```

#### 2. Update `App\Models\Music` model
```php
public function realms(): BelongsToMany
{
    return $this->belongsToMany(Realm::class, 'music_realm');
}
```

#### 3. Update `App\Models\Collection` model
```php
public function realms(): BelongsToMany
{
    return $this->belongsToMany(Realm::class, 'collection_realm');
}
```

#### 4. Update `App\Models\User` model
```php
protected $fillable = [
    // ... existing fields
    'current_realm_id',
];

public function currentRealm(): BelongsTo
{
    return $this->belongsTo(Realm::class, 'current_realm_id');
}
```

#### 5. Update `App\Models\MusicPlan` model
```php
protected $fillable = [
    'user_id',
    'realm_id', // replace 'setting'
    'is_published',
];

public function realm(): BelongsTo
{
    return $this->belongsTo(Realm::class);
}

// Update settingOptions() method
public static function realmOptions(): array
{
    return Realm::options();
}

// Add accessor for backward compatibility if needed
public function getSettingAttribute(): ?string
{
    return $this->realm?->name;
}
```

#### 6. Delete `App\MusicPlanSetting` enum (after all references updated)

### UI/View Updates

#### 1. Update `resources/views/components/music-plan-setting-icon.blade.php`
```blade
@php
    // Accept either Realm model or realm name
    if ($setting instanceof \App\Models\Realm) {
        $realm = $setting;
    } else {
        $realm = \App\Models\Realm::where('name', $setting)->first();
    }
    $icon = $realm?->icon() ?? 'other';
@endphp

@if($icon === 'organist')
    <x-gameicon-pipe-organ class="h-10 w-10 text-zinc-600 dark:text-zinc-600" />
@elseif($icon === 'guitar')
    <flux:icon name="guitar" class="h-10 w-10" />
@else
    <flux:icon name="musical-note" class="h-10 w-10" variant="outline" />
@endif
```

#### 2. Update all references to `MusicPlanSetting::tryFrom($setting)?->label()`
- In `⚡music-plan-card.blade.php`: Use `$musicPlan->realm?->label() ?? $musicPlan->realm?->name`
- In `⚡liturgical-info.blade.php`: Use `$plan->realm?->label() ?? $plan->realm?->name`
- In `music-plan-editor.blade.php`: Update component usage

#### 3. Update any forms that use `MusicPlan::settingOptions()`
Replace with `MusicPlan::realmOptions()` or `Realm::options()`

### Data Migration (if keeping existing data)
Since user indicated they'll delete existing data, we can:
1. Truncate relevant tables or run fresh migrations
2. Seed realms table with initial values: 'organist', 'guitarist', 'other'

### Testing Strategy

#### 1. Create tests for Realm model
```php
test('realm has correct label', function () {
    $realm = Realm::factory()->create(['name' => 'organist']);
    expect($realm->label())->toBe('Organist');
});

test('realm has many music', function () {
    $realm = Realm::factory()->create();
    $music = Music::factory()->count(3)->create();
    $realm->music()->attach($music);
    
    expect($realm->music)->toHaveCount(3);
});

test('user can have current realm', function () {
    $realm = Realm::factory()->create();
    $user = User::factory()->create(['current_realm_id' => $realm->id]);
    
    expect($user->currentRealm->id)->toBe($realm->id);
});
```

#### 2. Update existing tests
- Update `MusicPlanFactory` to use `realm_id` instead of `setting`
- Update any tests that assert on setting values

#### 3. Test UI components
- Test that realm icons display correctly
- Test that realm labels are translated

### Implementation Steps

1. **Create migrations**
   - Create realms table
   - Create music_realm pivot table
   - Create collection_realm pivot table
   - Add current_realm_id to users table
   - Update music_plans table (drop setting, add realm_id)

2. **Create Realm model and factory**
   - Create `App\Models\Realm`
   - Create `Database\Factories\RealmFactory`
   - Create `Database\Seeders\RealmSeeder` with initial values

3. **Update existing models**
   - Update Music, Collection, User, MusicPlan models

4. **Update UI components**
   - Update blade templates and components
   - Update Livewire components if they reference setting

5. **Run migrations and seed data**
   - Run migrations
   - Seed realms table

6. **Update tests**
   - Create new tests for Realm
   - Update existing tests

7. **Delete MusicPlanSetting enum**
   - Remove all references first
   - Delete `app/MusicPlanSetting.php`

8. **Verify functionality**
   - Test creating music plans with realms
   - Test assigning music to realms
   - Test user current realm selection

### Potential Issues and Mitigations

1. **Data loss**: Since user will delete existing data, no migration of existing setting values needed.

2. **Performance**: Many-to-many relationships could impact performance with large datasets. Consider eager loading where needed.

3. **Backward compatibility**: If needed during transition, keep `setting` column temporarily and add accessor/mutator to sync with `realm_id`.

4. **UI consistency**: Ensure all places that displayed setting now display realm correctly.

### Mermaid Diagram of New Relationships

```mermaid
erDiagram
    User ||--o{ Realm : "current_realm"
    User ||--o{ MusicPlan : creates
    MusicPlan }o--|| Realm : belongs_to
    Music }o--o{ Realm : "many-to-many"
    Collection }o--o{ Realm : "many-to-many"
    Music }o--o{ Collection : "many-to-many"
    
    User {
        bigint id PK
        string name
        string email
        bigint current_realm_id FK
    }
    
    Realm {
        bigint id PK
        string name
    }
    
    MusicPlan {
        bigint id PK
        bigint user_id FK
        bigint realm_id FK
        boolean is_published
    }
    
    Music {
        bigint id PK
        string title
    }
    
    Collection {
        bigint id PK
        string title
    }
    
    music_realm {
        bigint id PK
        bigint music_id FK
        bigint realm_id FK
    }
    
    collection_realm {
        bigint id PK
        bigint collection_id FK
        bigint realm_id FK
    }
```

### Next Steps
1. Review this plan with stakeholders
2. Begin implementation in Code mode
3. Test thoroughly before deployment
4. Update documentation if needed