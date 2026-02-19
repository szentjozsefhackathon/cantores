# Visibility Scoping Trait Design

## Overview
A reusable trait for Laravel models that have owners and visibility states (public/private). The trait provides configurable scopes to filter models based on visibility and ownership.

## Current Model Analysis

### Existing Visibility Patterns

**Music, Collection, Author, MusicPlan Models**
- Use `is_private` boolean field
- `false` = public, `true` = private
- Ownership field: `user_id`
- Existing scopes: `scopePublic()`, `scopePrivate()`, `scopeVisibleTo()`

### Common Logic
All models follow the same access logic:
- A model is accessible if it is public **OR** the authenticated user is the owner
- For guests, only public items are accessible

## Trait Design

### File Location
`app/Concerns/HasVisibilityScoping.php`

### Configuration Properties
Models can override these protected properties:

```php
protected string $visibilityField = 'is_private';
protected bool $visibilityPublicValue = false;
protected string $ownerField = 'user_id';
```

### Provided Scopes

1. **`scopePublic()`** - Returns items that are public according to configured field/value
2. **`scopePrivate()`** - Returns items that are private
3. **`scopeVisibleTo(?User $user)`** - Returns items that are public OR owned by the given user
4. **`scopeWithVisibleRelation(string $relation, ?User $user)`** - Helper for cascading visibility constraints

### Additional Methods

- **`isVisibleTo(?User $user)`** - Instance-level visibility check
- **`getVisibilityField()`**, **`getOwnerField()`**, **`getVisibilityPublicValue()`** - Configuration getters

## Implementation Details

### Trait Code
```php
<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasVisibilityScoping
{
    protected string $visibilityField = 'is_private';
    protected bool $visibilityPublicValue = false;
    protected string $ownerField = 'user_id';

    public function scopePublic(Builder $query): Builder
    {
        return $query->where($this->visibilityField, $this->visibilityPublicValue);
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where($this->visibilityField, ! $this->visibilityPublicValue);
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $userId = $user?->id;

        if (! $userId) {
            return $this->scopePublic($query);
        }

        return $query->where(function (Builder $q) use ($userId) {
            $q->where($this->visibilityField, $this->visibilityPublicValue)
                ->orWhere($this->ownerField, $userId);
        });
    }

    public function scopeWithVisibleRelation(Builder $query, string $relation, ?User $user = null): Builder
    {
        return $query->whereHas($relation, function (Builder $q) use ($user) {
            /** @var Model|HasVisibilityScoping $model */
            $model = $q->getModel();
            
            if (in_array(HasVisibilityScoping::class, class_uses_recursive($model))) {
                $model->scopeVisibleTo($q, $user);
            }
        });
    }

    public function isVisibleTo(?User $user = null): bool
    {
        $isPublic = $this->getAttribute($this->visibilityField) === $this->visibilityPublicValue;
        
        if ($isPublic) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $this->getAttribute($this->ownerField) === $user->id;
    }

    public function getVisibilityField(): string
    {
        return $this->visibilityField;
    }

    public function getOwnerField(): string
    {
        return $this->ownerField;
    }

    public function getVisibilityPublicValue(): bool
    {
        return $this->visibilityPublicValue;
    }
}
```

## Migration Plan

### Step 1: Create the Trait
Create `app/Concerns/HasVisibilityScoping.php` with the above code.

### Step 2: Update Existing Models

**Music Model:**
```php
use App\Concerns\HasVisibilityScoping;

class Music extends Model
{
    use HasVisibilityScoping;
    
    // Remove existing scopePublic(), scopePrivate(), scopeVisibleTo() methods
}
```

**Collection Model:**
```php
use App\Concerns\HasVisibilityScoping;

class Collection extends Model
{
    use HasVisibilityScoping;
    
    // Remove existing visibility scopes
}
```

**Author Model:**
```php
use App\Concerns\HasVisibilityScoping;

class Author extends Model
{
    use HasVisibilityScoping;
    
    // Remove existing visibility scopes
}
```

**MusicPlan Model:**
```php
use App\Concerns\HasVisibilityScoping;

class MusicPlan extends Model
{
    use HasVisibilityScoping;
    
    // Remove existing visibility scopes
}
```

### Step 3: Update Usage in Controllers/Livewire Components
Search for existing usage of `->public()`, `->private()`, `->visibleTo()` and ensure they still work (they will, as trait provides same interface).

### Step 4: Add Tests
Create feature tests to verify:
- Public scope works with different field configurations
- VisibleTo scope works for guests and authenticated users
- Cascading visibility with `withVisibleRelation`

## Configuration Examples

### Standard Model (is_private field)
```php
class Music extends Model
{
    use HasVisibilityScoping;
    // Uses defaults: is_private field, false = public, user_id owner
}
```

### Custom Owner Field
```php
class Document extends Model
{
    use HasVisibilityScoping;
    
    protected string $ownerField = 'author_id';
}
```

## Cascading Visibility Example

When loading a Music with its Authors, ensure only visible authors are included:

```php
// Before: Manual whereHas
Music::whereHas('authors', function ($query) use ($user) {
    $query->where('is_private', false)
        ->when($user, fn($q) => $q->orWhere('user_id', $user->id));
});

// After: Using trait helper
Music::withVisibleRelation('authors', $user);
```

## Edge Cases Considered

1. **Nullable User**: `scopeVisibleTo(null)` returns only public items
2. **Guest Access**: Properly handles unauthenticated users
3. **Custom Field Names**: Configurable via properties
5. **Cascading Constraints**: Helper method for applying visibility to relations

## Benefits

1. **DRY Principle**: Eliminates duplicate scope code across models
2. **Consistency**: Ensures uniform visibility logic application
3. **Configurable**: Adapts to different field naming conventions
4. **Testable**: Centralized logic makes testing easier
5. **Extensible**: Easy to add new visibility-related functionality

## Next Steps

1. Review and approve the trait design
2. Switch to Code mode for implementation
3. Update existing models to use the trait
4. Run tests to ensure backward compatibility
5. Update documentation if needed