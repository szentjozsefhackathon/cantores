# Role System Implementation Plan

## Overview
Implement a three-role system (Admin, Editor, Contributor) with specific permissions based on user requirements.

## Roles and Permissions

### 1. Admin Role
- **Can assign roles** to users (`role.assign` permission)
- **Can edit published musics and collections** (but not unpublished)
- **Cannot edit others' music plans** (strict ownership rule)
- **Can unpublish** music, collection, and music plan
- **Has system administration access** (`access.admin` permission)
- **Gets all permissions** for backward compatibility

### 2. Editor Role
- **Can edit published musics and published collections**
- **Cannot edit unpublished content** (only Contributors can edit their own unpublished work)
- **Can unpublish** music, collection, and music plan
- **Cannot assign roles**
- **Cannot create new content** (only Contributors create)

### 3. Contributor Role (Default)
- **Can create** collections, musics, music plans
- **Can edit their own creations** (regardless of published status)
- **Cannot edit others' content**
- **Cannot unpublish content** (except their own by editing)
- **Automatically assigned** to new registered users

## Special Rules

### Music Plan Ownership
- **Strict ownership**: Only the owner can edit their music plan
- **Applies to all roles**: Even Admins cannot edit others' music plans
- **Unpublish exception**: Editors/Admins can unpublish any music plan

### Published vs Unpublished Content
- **Music Plans**: Have `is_published` field (already exists)
- **Musics & Collections**: Need `is_published` field added (see Database Changes)
- **Editing rules**:
  - Contributors: Can edit their own (published or unpublished)
  - Editors/Admins: Can edit only when published
  - Everyone: Can view published content

### Unpublish Action
- Editors/Admins can unpublish any content
- Unpublished content is only visible to the Contributor (owner)
- Purpose: Quality control - Editors can remove poor quality content from public view

## Database Changes

### 1. Add `is_published` fields
```php
// Migration for musics table
Schema::table('musics', function (Blueprint $table) {
    $table->boolean('is_published')->default(false);
});

// Migration for collections table  
Schema::table('collections', function (Blueprint $table) {
    $table->boolean('is_published')->default(false);
});
```

### 2. Update RolePermissionSeeder
See `database/seeders/RolePermissionSeeder.php` for complete implementation.

## Code Changes

### 1. User Model Updates (`app/Models/User.php`)
- Update `getIsAdminAttribute()` to check for admin role
- Add method to assign contributor role on registration
- Add helper methods for role checks

### 2. Policy Updates

#### MusicPlanPolicy (`app/Policies/MusicPlanPolicy.php`)
- **view**: Owner can view, others can view only if published
- **update**: Only owner can update (strict ownership)
- **delete**: Only owner can delete
- **unpublish**: Owner, Editor, or Admin can unpublish

#### MusicPolicy (`app/Policies/MusicPolicy.php`)
- **view**: Anyone can view if published, owner can always view
- **update**: Owner can always update, Editor/Admin can update only if published
- **delete**: Only owner can delete
- **unpublish**: Owner, Editor, or Admin can unpublish

#### CollectionPolicy (`app/Policies/CollectionPolicy.php`)
- Same logic as MusicPolicy

### 3. AdminMiddleware Update (`app/Http/Middleware/AdminMiddleware.php`)
- Check for `admin` role instead of email-based admin check
- Keep backward compatibility

### 4. Registration Flow (`app/Actions/Fortify/CreateNewUser.php`)
- Automatically assign `contributor` role to new users

## Permission Matrix

| Permission | Admin | Editor | Contributor |
|------------|-------|--------|-------------|
| `music.view` | ✓ | ✓ | ✓ |
| `music.create` | ✓ | ✗ | ✓ |
| `music.update` (own) | ✓* | ✓* | ✓ |
| `music.update` (others) | ✓* | ✓* | ✗ |
| `music.delete` | ✓ | ✗ | ✓ (own only) |
| `music.unpublish` | ✓ | ✓ | ✗ |
| `collection.view` | ✓ | ✓ | ✓ |
| `collection.create` | ✓ | ✗ | ✓ |
| `collection.update` (own) | ✓* | ✓* | ✓ |
| `collection.update` (others) | ✓* | ✓* | ✗ |
| `collection.delete` | ✓ | ✗ | ✓ (own only) |
| `collection.unpublish` | ✓ | ✓ | ✗ |
| `music-plan.view` | ✓ | ✓ | ✓ |
| `music-plan.create` | ✓ | ✗ | ✓ |
| `music-plan.update` (own) | ✓ | ✗ | ✓ |
| `music-plan.update` (others) | ✗ | ✗ | ✗ |
| `music-plan.delete` | ✓ | ✗ | ✓ (own only) |
| `music-plan.unpublish` | ✓ | ✓ | ✗ |
| `role.assign` | ✓ | ✗ | ✗ |
| `access.admin` | ✓ | ✗ | ✗ |

*Note: ✓* = Only when published

## Implementation Steps

### Phase 1: Database Setup
1. Create migrations for `is_published` fields
2. Run `RolePermissionSeeder` to create roles and permissions
3. Assign roles to existing users

### Phase 2: Core Code Updates
1. Update User model
2. Update all policies with new logic
3. Update AdminMiddleware
4. Update registration flow

### Phase 3: Testing
1. Create comprehensive tests for each role
2. Test edge cases (ownership, published status)
3. Update existing tests to use role system

### Phase 4: UI Updates (Optional)
1. Add role indicators in admin panel
2. Add unpublish buttons for Editors/Admins
3. Update visibility indicators for unpublished content

## Testing Strategy

### Test Cases
1. **Contributor can create and edit their own content**
2. **Contributor cannot edit others' content**
3. **Editor can edit published content (not unpublished)**
4. **Editor cannot edit music plans (strict ownership)**
5. **Editor can unpublish any content**
6. **Admin can assign roles**
7. **Admin cannot edit others' music plans**
8. **Published content is visible to all**
9. **Unpublished content is only visible to owner**

### Test Helpers
```php
// In tests
protected function createUserWithRole($role)
{
    $user = User::factory()->create();
    $user->assignRole($role);
    return $user;
}
```

## Migration Considerations

### Backward Compatibility
- Keep `is_admin` attribute working (checks both email and role)
- Existing admin users get `admin` role automatically
- All other users get `contributor` role
- Policies should handle both old and new authorization

### Data Migration
- All existing musics/collections should be marked as `is_published = true`
- This maintains existing visibility

## Files to Create/Modify

### New Files
- `database/migrations/YYYY_MM_DD_HHMMSS_add_is_published_to_musics_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_is_published_to_collections_table.php`

### Modified Files
- `database/seeders/RolePermissionSeeder.php`
- `app/Models/User.php`
- `app/Policies/MusicPlanPolicy.php`
- `app/Policies/MusicPolicy.php`
- `app/Policies/CollectionPolicy.php`
- `app/Http/Middleware/AdminMiddleware.php`
- `app/Actions/Fortify/CreateNewUser.php`
- `tests/Feature/RoleAuthorizationTest.php` (new test file)
- Update existing test files to use roles

## Next Steps
1. Switch to Code mode to implement database changes
2. Implement RolePermissionSeeder
3. Update policies with new logic
4. Create comprehensive tests
5. Run full test suite to ensure compatibility