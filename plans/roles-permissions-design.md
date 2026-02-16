# Roles and Permissions System Design

## Overview
This document outlines the design for implementing a comprehensive role-based permission system using Spatie Laravel Permission package. The system will replace the current email-based admin check with a more flexible role-based approach.

## Current State Analysis
- Spatie Laravel Permission package is installed but not utilized
- Admin access is determined by email matching `ADMIN_EMAIL` config
- Policies exist for core resources but use simple ownership checks
- Permission tables exist but are empty
- User model already has `HasRoles` trait

## Role Definitions

### 1. Admin
- **Description**: Full system access, can manage all resources and users
- **Permissions**: All permissions
- **Use Case**: System administrators, super users

### 2. Editor  
- **Description**: Can create and edit content, but limited to their genre
- **Permissions**: Create/read/update content, but not delete system-level resources
- **Use Case**: Music directors, content managers

### 3. Viewer
- **Description**: Read-only access to content
- **Permissions**: Read permissions only
- **Use Case**: Regular users, guests with accounts

### 4. Guest (No role)
- **Description**: Unauthenticated users
- **Permissions**: Limited public access only

## Permission Matrix

### Resource-Based Permissions

#### Music Management
- `music.view` - View music pieces
- `music.create` - Create new music pieces  
- `music.update` - Update music pieces
- `music.delete` - Delete music pieces
- `music.manage` - Full management (includes all above)

#### Collection Management
- `collection.view` - View collections
- `collection.create` - Create new collections
- `collection.update` - Update collections
- `collection.delete` - Delete collections
- `collection.manage` - Full management

#### Music Plan Management
- `music-plan.view` - View music plans
- `music-plan.create` - Create new music plans
- `music-plan.update` - Update music plans  
- `music-plan.delete` - Delete music plans
- `music-plan.manage` - Full management

#### Music Plan Templates (Admin-only)
- `music-plan-template.view` - View templates
- `music-plan-template.create` - Create templates
- `music-plan-template.update` - Update templates
- `music-plan-template.delete` - Delete templates
- `music-plan-template.manage` - Full management

#### Celebration Management
- `celebration.view` - View celebrations
- `celebration.create` - Create celebrations
- `celebration.update` - Update celebrations
- `celebration.delete` - Delete celebrations
- `celebration.manage` - Full management

#### User Management (Admin-only)
- `user.view` - View users
- `user.create` - Create users
- `user.update` - Update users
- `user.delete` - Delete users
- `user.manage` - Full management

#### Genre Management (Admin-only)
- `genre.view` - View genres
- `genre.create` - Create genres
- `genre.update` - Update genres
- `genre.delete` - Delete genres
- `genre.manage` - Full management

#### System Administration
- `access.admin` - Access admin panel
- `manage.roles` - Manage roles and permissions
- `system.settings` - Manage system settings

## Role-Permission Mapping

### Admin Role
- All permissions (`*`)

### Editor Role
- `music.view`, `music.create`, `music.update`, `music.delete`
- `collection.view`, `collection.create`, `collection.update`, `collection.delete`
- `music-plan.view`, `music-plan.create`, `music-plan.update`, `music-plan.delete`
- `celebration.view`, `celebration.create`, `celebration.update`, `celebration.delete`
- Limited to their current genre (enforced via policies)

### Viewer Role
- `music.view`
- `collection.view` 
- `music-plan.view`
- `celebration.view`
- Limited to their current genre (enforced via policies)

## Genre-Based Permissions

The application has a genre system where users belong to genres. Permissions should respect genre boundaries:

1. **Admin**: Can access all genres
2. **Editor**: Can only manage content in their current genre
3. **Viewer**: Can only view content in their current genre

This will be enforced through:
- Query scopes on models
- Policy checks that verify `genre_id` matches user's `current_genre_id`
- Middleware for genre-specific routes

## Migration Strategy

### Phase 1: Database Setup
1. Create roles and permissions seeder
2. Assign admin role to existing admin users (based on current `ADMIN_EMAIL`)
3. Assign viewer role to all other existing users

### Phase 2: Policy Updates
1. Update existing policies to check roles in addition to ownership
2. Add genre-based authorization checks
3. Maintain backward compatibility during transition

### Phase 3: Middleware Updates
1. Update `AdminMiddleware` to check for `admin` role instead of email
2. Create new middleware for editor and viewer roles if needed

### Phase 4: UI Updates (Optional)
1. Add role management interface in admin panel
2. Update user management to assign roles
3. Add visual indicators for user roles

## Database Schema

The Spatie package already provides the necessary tables:
- `roles` - Role definitions
- `permissions` - Permission definitions  
- `role_has_permissions` - Role-permission mapping
- `model_has_roles` - User-role assignments
- `model_has_permissions` - Direct user-permission assignments (rarely used)

## Implementation Details

### 1. Seeder Structure
```php
// database/seeders/RolePermissionSeeder.php
$adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
$editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
$viewerRole = Role::create(['name' => 'viewer', 'guard_name' => 'web']);

// Create permissions for each resource
$permissions = [
    'music' => ['view', 'create', 'update', 'delete', 'manage'],
    'collection' => ['view', 'create', 'update', 'delete', 'manage'],
    // ... etc
];

// Assign permissions to roles
$adminRole->givePermissionTo(Permission::all());
$editorRole->givePermissionTo([...]);
$viewerRole->givePermissionTo([...]);
```

### 2. Policy Updates
```php
// app/Policies/MusicPolicy.php
public function view(User $user, Music $music): bool
{
    // Admin can view anything
    if ($user->hasRole('admin')) {
        return true;
    }
    
    // Editor/Viewer can only view music in their genre
    if ($user->current_genre_id !== $music->genre_id) {
        return false;
    }
    
    // Check role-based permission
    return $user->hasPermissionTo('music.view');
}
```

### 3. Middleware Updates
```php
// app/Http/Middleware/AdminMiddleware.php
public function handle(Request $request, Closure $next)
{
    if (! Auth::check() || ! Auth::user()->hasRole('admin')) {
        abort(403);
    }
    
    return $next($request);
}
```

## Testing Strategy

1. Update existing tests to use roles instead of email-based admin checks
2. Create new tests for role-based authorization
3. Test genre-based permission boundaries
4. Test role assignment and permission inheritance

## Backward Compatibility

To maintain compatibility during migration:
1. Keep `is_admin` attribute on User model (can be computed from roles)
2. Update existing code to use `hasRole('admin')` instead of `is_admin`
3. Provide migration path for existing admin users

## Next Steps

1. Create the database seeder with roles and permissions
2. Update User model to sync roles with existing admin status
3. Update policies to incorporate role checks
4. Update middleware and route protections
5. Update tests to reflect new authorization system
6. Optional: Create admin UI for role management