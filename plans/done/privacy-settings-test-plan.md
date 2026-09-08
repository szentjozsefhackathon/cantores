# Privacy Settings Manual Test Plan

## Introduction
This document outlines manual test cases to verify that privacy settings are correctly enforced across different user roles in the application. The tests cover visibility of music, music plans, collections, authors, and suggestions, as well as editing permissions and user name display.

## Roles and Permissions Overview

| Role | Permissions | Description |
|------|-------------|-------------|
| Guest (Non‑authenticated) | None | Can only view public (published) content; no editing capabilities. |
| Contributor | `content.create`, `content.edit.own` | Can create content and edit own content; can view public content and own private content. |
| Editor | `content.create`, `content.edit.own`, `content.edit.published`, `content.edit.verified`, `masterdata.maintain` | Can edit any published content, edit verified music, maintain masterdata; cannot access admin‑only areas. |
| Admin | All permissions (`content.*`, `masterdata.maintain`, `system.maintain`) | Full access to all features, including system administration. |

## Test Environment Setup
1. Ensure the application is running in a testing environment (or production with care).
2. Have at least one user of each role (contributor, editor, admin) created and known credentials.
3. Create test data:
   - Public and private music, music plans, collections, authors owned by different users.
   - Verified music items.
   - Music plans that reference both public and private music.
4. Use a fresh browser session or incognito window for each role to avoid authentication interference.

---

## Test Cases by Role

### Guest (Non‑authenticated)

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| G‑01 | Cannot access authenticated routes | 1. Navigate to `/dashboard`. <br> 2. Navigate to `/my‑music‑plans`. <br> 3. Navigate to `/editor/musics`. | Redirected to login page for each attempt. |
| G‑02 | Can view a published music plan | 1. Obtain the URL of a music plan with `is_private = false`. <br> 2. Open the URL while not logged in. | Music plan details are displayed; **user badge (display name) is not visible**. |
| G‑03 | Cannot view a private music plan | 1. Obtain the URL of a music plan with `is_private = true`. <br> 2. Open the URL while not logged in. | HTTP 403 Forbidden (or redirect to login). |
| G‑04 | Can view public music | 1. Navigate to `/music/{id}/view` for a music with `is_private = false`. | Music details are displayed. |
| G‑05 | Cannot view private music | 1. Navigate to `/music/{id}/view` for a music with `is_private = true`. | HTTP 403 Forbidden (or redirect to login). |
| G‑06 | Can view public collection | 1. Navigate to `/collection/{id}/view` for a collection with `is_private = false`. | Collection details are displayed. |
| G‑07 | Cannot view private collection | 1. Navigate to `/collection/{id}/view` for a collection with `is_private = true`. | HTTP 403 Forbidden. |
| G‑08 | Can view public author | 1. Navigate to `/author/{id}/view` for an author with `is_private = false`. | Author details are displayed. |
| G‑09 | Cannot view private author | 1. Navigate to `/author/{id}/view` for an author with `is_private = true`. | HTTP 403 Forbidden. |
| G‑10 | Suggestions page shows only public content | 1. Navigate to `/suggestions`. <br> 2. Observe the list of music plans and music suggestions. | Only music plans with `is_private = false` appear; only public music is suggested; no user badges are shown. |
| G‑11 | No user identifiers visible anywhere | 1. Browse all pages accessible to guests. | Nowhere is a user’s display name (city + first name) or any other user identifier shown. |

### Contributor

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| C‑01 | Can view own private music | 1. Log in as a contributor. <br> 2. Navigate to the detail page of a music item you own that is private. | Music details are displayed. |
| C‑02 | Cannot view another user’s private music | 1. Log in as a contributor. <br> 2. Try to access a private music owned by a different user. | HTTP 403 Forbidden. |
| C‑03 | Can view all public music | 1. Log in as a contributor. <br> 2. Visit the music listing (`/musics`). | All public music items are visible. |
| C‑04 | Can edit own unverified music | 1. Log in as a contributor. <br> 2. Go to the editor page of a music you own that is not verified. <br> 3. Make a change and save. | Change is accepted. |
| C‑05 | Cannot edit another user’s public music | 1. Log in as a contributor. <br> 2. Try to access the editor page of a public music owned by another user. | HTTP 403 Forbidden (or editor UI not available). |
| C‑06 | Cannot edit verified music (requires `content.edit.verified`) | 1. Log in as a contributor. <br> 2. Attempt to edit a verified music item (owned by anyone). | HTTP 403 Forbidden. |
| C‑07 | Can delete own unverified music | 1. Log in as a contributor. <br> 2. On a music you own that is not verified, trigger deletion. | Deletion succeeds. |
| C‑08 | Cannot delete verified music | 1. Log in as a contributor. <br> 2. Attempt to delete a verified music item. | Deletion is prevented (403 or error). |
| C‑09 | Cannot delete another user’s music | 1. Log in as a contributor. <br> 2. Attempt to delete a music item owned by another user (public or private). | HTTP 403 Forbidden. |
| C‑10 | Can create new music, collection, author, music plan | 1. Log in as a contributor. <br> 2. Use the respective creation UI for each resource. | Resource is created and owned by the contributor. |
| C‑11 | Can view own private music plan | 1. Log in as a contributor. <br> 2. Navigate to a music plan you own that is private. | Plan details are displayed. |
| C‑12 | Cannot view another user’s private music plan | 1. Log in as a contributor. <br> 2. Try to access a private music plan owned by another user. | HTTP 403 Forbidden. |
| C‑13 | Suggestions include own private music | 1. Log in as a contributor. <br> 2. Go to `/suggestions`. <br> 3. Verify that your own private music appears in suggestions when appropriate. | Private music you own is suggested; other users’ private music is not. |
| C‑14 | User badge visible for own content | 1. View a music plan you own (private or public). | Your display name (city + first name) is shown in the user badge. |
| C‑15 | User badge visible for other users’ public content | 1. View a public music plan owned by another user. | That user’s display name is shown. |

### Editor

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| E‑01 | Can edit any published music (not owned) | 1. Log in as an editor. <br> 2. Open the editor page of a public music owned by another user. <br> 3. Make a change and save. | Change is accepted. |
| E‑02 | Can edit verified music | 1. Log in as an editor. <br> 2. Open the editor page of a verified music item. <br> 3. Make a change and save. | Change is accepted. |
| E‑03 | Cannot edit another user’s private music | 1. Log in as an editor. <br> 2. Attempt to edit a private music owned by another user. | HTTP 403 Forbidden. |
| E‑04 | Can delete any published music | 1. Log in as an editor. <br> 2. Delete a public music item owned by another user (non‑verified). | Deletion succeeds. |
| E‑05 | Can delete verified music | 1. Log in as an editor. <br> 2. Delete a verified music item. | Deletion succeeds. |
| E‑06 | Cannot delete another user’s private music | 1. Log in as an editor. <br> 2. Attempt to delete a private music owned by another user. | HTTP 403 Forbidden. |
| E‑07 | Cannot edit another user’s collection (policy restricts to owner/admin) | 1. Log in as an editor. <br> 2. Try to edit a collection owned by another user (even if public). | HTTP 403 Forbidden. |
| E‑08 | Cannot edit another user’s author (policy restricts to owner/admin) | 1. Log in as an editor. <br> 2. Try to edit an author owned by another user. | HTTP 403 Forbidden. |
| E‑09 | Can maintain masterdata (genres, cities, first names) | 1. Log in as an editor. <br> 2. Navigate to the admin area for masterdata (if separate). <br> 3. Verify you can view and modify genres, cities, first names. | Access granted; modifications possible. |
| E‑10 | Cannot access admin‑only pages (bulk import, music plan templates, role permissions) | 1. Log in as an editor. <br> 2. Try to visit `/admin/bulk‑imports`, `/admin/music‑plan‑templates`, `/admin/role‑permissions`. | HTTP 403 Forbidden or page not found. |
| E‑11 | Suggestions include all public music and own private music | 1. Log in as an editor. <br> 2. Go to `/suggestions`. <br> 3. Verify that all public music appears, plus your own private music. | No private music of other users appears. |

### Admin

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| A‑01 | Can view any music, collection, author, music plan (private or public) | 1. Log in as admin. <br> 2. Navigate to detail pages of private resources owned by any user. | Details displayed without restriction. |
| A‑02 | Can edit any resource (including private) | 1. Log in as admin. <br> 2. Edit a private music, collection, author, music plan owned by another user. | Changes saved successfully. |
| A‑03 | Can delete any resource | 1. Log in as admin. <br> 2. Delete a private music, collection, author, music plan owned by another user. | Deletion succeeds. |
| A‑04 | Can access all admin pages | 1. Log in as admin. <br> 2. Visit `/admin/bulk‑imports`, `/admin/music‑plan‑templates`, `/admin/role‑permissions`, `/admin/users`. | Pages load without error. |
| A‑05 | Can create and assign roles/permissions | 1. Log in as admin. <br> 2. Go to role‑permission manager. <br> 3. Create a new role, assign permissions, assign to a user. | Role creation and assignment succeed. |
| A‑06 | Can verify/reject music verifications | 1. Log in as admin. <br> 2. Navigate to music verification page. <br> 3. Approve or reject a pending verification. | Action succeeds. |
| A‑07 | Can create global music plan slots | 1. Log in as admin. <br> 2. Go to admin music plan slots page. <br> 3. Create a new global slot. | Slot is created and available to all users. |
| A‑08 | Suggestions include all music (including other users’ private) | 1. Log in as admin. <br> 2. Go to `/suggestions`. <br> 3. Verify that private music of other users appears (if referenced in visible plans). | All music is visible (subject to visibility scoping). |

---

## Cross‑cutting Test Cases

### Suggestions Privacy

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| S‑01 | Guest suggestions only include public music | As per G‑10. | |
| S‑02 | Contributor suggestions include own private music | As per C‑13. | |
| S‑03 | Editor suggestions include all public music | As per E‑11. | |
| S‑04 | Admin suggestions include all music | As per A‑08. | |

### User Name Display

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| U‑01 | Guests never see user badges | As per G‑11. | |
| U‑02 | Authenticated users see user badges for public content | As per C‑15. | |
| U‑03 | Authenticated users see their own badge on own content | As per C‑14. | |

### Referenced Private Content in Published Plans

| ID | Description | Steps | Expected Result |
|----|-------------|-------|-----------------|
| R‑01 | Private music referenced in a published music plan is not visible to guests | 1. Create a published music plan that includes a private music (owned by any user). <br> 2. View the plan as a guest. | The private music entry is omitted or marked as inaccessible. |
| R‑02 | Private music referenced in a published music plan is visible to the owner | 1. As owner of the private music, view the published plan that references it. | The music is displayed. |
| R‑03 | Private music referenced in a published music plan is **not** visible to other contributors/editors | 1. As a different contributor/editor, view the published plan. | The private music is hidden (or shows a “no access” placeholder). |
| R‑04 | Private music referenced in a published music plan is visible to admin | 1. As admin, view the published plan. | The music is displayed. |

---

## Appendix: How to Execute the Tests

1. **Prepare test data** using the application’s UI or tinker console.
2. **Use separate browser sessions** (or incognito windows) for each role to avoid cookie interference.
3. **Record results** in a spreadsheet or test management tool.
4. **Note any deviations** from expected behavior and report as bugs.

### Quick Data Setup Commands (via tinker)

```php
// Create users with roles (adjust as needed)
$contributor = User::factory()->create();
$contributor->assignRole('contributor');

$editor = User::factory()->create();
$editor->assignRole('editor');

$admin = User::factory()->create();
$admin->assignRole('admin');

// Create public/private music for different owners
Music::factory()->create(['user_id' => $contributor->id, 'is_private' => false]);
Music::factory()->create(['user_id' => $contributor->id, 'is_private' => true]);
Music::factory()->create(['user_id' => $editor->id, 'is_private' => false]);
// etc.
```

---

## Revision History

| Date | Version | Changes |
|------|---------|---------|
| 2026‑02‑21 | 1.0 | Initial test plan created. |

