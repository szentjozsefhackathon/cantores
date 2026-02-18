# Admin Role Migration Plan

## Objective
Change the User `isAdmin` attribute getter to check for the "admin" role instead of email matching. Update tests to assign admin role rather than using special email.

## Current State
- `app/Models/User.php` has `getIsAdminAttribute()` that compares user email with `config('admin.email')`
- There is no `isAdmin()` method, but several FormRequest classes call `$user->isAdmin()` (incorrectly)
- Tests create admin users by setting email to `config('admin.email')` or hardcoded `admin@example.com`
- Spatie Permission package is installed and an "admin" role exists in database

## Proposed Changes

### 1. Update User Model
**File:** `app/Models/User.php`

**Current method:**
```php
public function getIsAdminAttribute(): bool
{
    $adminEmail = config('admin.email', env('ADMIN_EMAIL'));

    return $this->email === $adminEmail;
}
```

**New method:**
```php
public function getIsAdminAttribute(): bool
{
    return $this->hasRole('admin');
}
```

**Add `isAdmin()` method for compatibility:**
```php
public function isAdmin(): bool
{
    return $this->isAdmin;
}
```

### 2. Update FormRequest Classes (Optional)
The FormRequest classes currently call `$this->user()?->isAdmin()`. With the new `isAdmin()` method added, they will work correctly. No changes needed.

### 3. Update Tests
We need to update all test files that create admin users via email to assign the "admin" role instead.

#### Test Files to Modify:

1. **`tests/Feature/Livewire/ErrorReportComponentTest.php`**
   - Line 49: `$admin = User::factory()->create(['email' => \Config::get('admin.email')]);`
   - Change to: Create user with random email, then assign admin role.

2. **`tests/Feature/MusicVerificationPolicyTest.php`**
   - Line 7: `$this->admin = User::factory()->create(['email' => 'admin@example.com']);`
   - Change to: Create user with random email, assign admin role.

3. **`tests/Feature/NotificationIntegrationTest.php`**
   - Lines 19-23: Creates admin user via config email and assigns role.
   - Already assigns role, but email is still special. Change to random email.

4. **`tests/Feature/MusicPlanSlotsTest.php`**
   - Lines 15-19: Creates admin with email `admin@example.com`
   - Change to random email and assign admin role.

5. **`tests/Feature/Services/NotificationServiceTest.php`**
   - Lines 22, 44: `$admin = User::factory()->create(['email' => \Config::get('admin.email')]);`
   - Change to random email and assign admin role.

#### Approach:
For each test, we should:
- Create a user with a random email (using factory's default or `fake()->unique()->email()`)
- Assign the "admin" role using `$user->assignRole('admin')`
- Ensure the admin role exists in the test setup (some tests already create it)

### 4. Verify Other Dependencies
- Check if any other code relies on `config('admin.email')` for purposes other than admin detection (e.g., sending notifications). Those should remain unchanged.
- Ensure admin middleware (`AdminMiddleware`) uses the `isAdmin` attribute correctly (should work with role check).

### 5. Database Considerations
- Existing admin users (with the special email) should have the "admin" role assigned manually or via migration.
- We may need to create a migration to assign the admin role to the user with the configured admin email (if exists). However, this is out of scope for this task.

### 6. Testing Strategy
- Run existing test suite after changes to ensure no regressions.
- Specifically test admin-only pages and actions with both admin and non-admin users.
- Ensure role-based authorization works as expected.

## Implementation Steps

1. **Switch to Code mode** and make the User model changes.
2. **Update each test file** following the patterns above.
3. **Run the test suite** to verify changes.
4. **If any failures**, debug and fix.
5. **Final verification** by running all tests and checking admin functionality manually.

## Risks
- If there are existing admin users without the "admin" role, they will lose admin privileges after the change. Need to ensure role assignment is done in production.
- Tests that rely on email matching for other purposes (e.g., notification routing) may break if we change email. We'll keep the email as is for those tests? Actually we can keep the email random; notification routing likely uses role, not email.

## Success Criteria
- `User::isAdmin` attribute returns true only for users with "admin" role.
- All existing tests pass.
- Admin-only functionality remains accessible to users with admin role.
- Non-admin users cannot access admin-only functionality.

## Notes
- The `config/admin.php` file and `ADMIN_EMAIL` environment variable may still be used for other purposes (e.g., initial admin creation). We can keep them.
- Consider adding a console command or seeder to assign admin role to the configured admin email for convenience.