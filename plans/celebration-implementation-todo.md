# Celebration Model Implementation - Action Plan

## Phase 1: Database Migrations
1. **Create celebrations table migration**
   - Fields: id, celebration_key (integer, default 0), actual_date (date), name (string), season (integer), season_text (string nullable), week (integer), day (integer), readings_code (string nullable), year_letter (char nullable), year_parity (string nullable), timestamps
   - Unique constraint: (actual_date, celebration_key)
   - Indexes: (season, week, day), (actual_date)

2. **Create celebration_music_plan pivot table migration**
   - Fields: id, celebration_id (foreign), music_plan_id (foreign), timestamps
   - Unique constraint: (celebration_id, music_plan_id)
   - Indexes: celebration_id, music_plan_id

3. **Remove celebration columns from music_plans table migration**
   - Columns to remove: celebration_name, actual_date, season, season_text, week, day, readings_code, year_letter, year_parity
   - Keep: user_id, setting, is_private, timestamps

## Phase 2: Models
4. **Create Celebration model** (app/Models/Celebration.php)
   - Fillable fields, casts, relationships
   - Relationship: belongsToMany MusicPlan

5. **Update MusicPlan model** (app/Models/MusicPlan.php)
   - Remove celebration fields from fillable and casts
   - Add relationship: belongsToMany Celebration
   - Update any methods that reference celebration fields (getDayNameAttribute, etc.)

## Phase 3: Update Liturgical Info Component
6. **Update createMusicPlan method** in liturgical-info.blade.php
   - Find or create Celebration using firstOrCreate
   - Create MusicPlan without celebration fields
   - Attach celebration via pivot

7. **Update getExistingMusicPlans method**
   - Query through celebrations relationship instead of direct fields

## Phase 4: Update Other Components
8. **Update MusicPlans Livewire component** (app/Livewire/Pages/MusicPlans.php)
   - Update search to join with celebrations table
   - Search celebration name, season_text, year_letter from celebrations

9. **Update music-plan-editor.blade.php**
   - Update references to $musicPlan->celebration_name, etc.
   - Fetch from first celebration or handle multiple

10. **Update music-plan-card.blade.php**
    - Display celebration data from relationship

11. **Update any other views** that reference MusicPlan celebration fields

## Phase 5: Testing
12. **Create Celebration factory** for testing
13. **Update existing tests** to use new structure
14. **Create new tests** for many-to-many relationship
15. **Test createMusicPlan functionality**

## Phase 6: Data Migration (if needed)
16. **Create data migration script** to move existing data
    - Since no production data, we can truncate tables and start fresh
    - Or migrate existing test data

## Notes
- celebration_key defaults to 0 for all records
- Unique constraint on (actual_date, celebration_key) ensures same celebration on same date with same key is unique
- Many-to-many allows one MusicPlan to be reused for multiple Celebrations
- Search through relationship requires JOIN queries