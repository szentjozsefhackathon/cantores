# Music Merging Test Plan

## Test Scenarios

### 1. Basic Merge - No Conflicts
**Setup**: Two music records with different but non-conflicting data
- Left: Title "A", subtitle "Sub A", genre "organist"
- Right: Title "B", subtitle "Sub B", genre "guitarist"
- Collections: Left in Collection X, Right in Collection Y

**Expected**:
- Merged title = "A" (left)
- Merged subtitle = "Sub A" (left)
- Genres = both "organist" and "guitarist"
- Collections = both X and Y
- No conflicts detected

### 2. Direct Field Conflicts
**Setup**: Same field, different values
- Title: "Hymn" vs "Psalm"
- Custom ID: "123" vs "456"
- Privacy: public vs private

**Expected**:
- Conflicts detected for title, custom_id, is_private
- UI shows conflict badges
- Default resolution: left values for title/custom_id, FALSE for privacy
- User can override before save

### 3. Collection Conflicts
**Setup**: Same collection, different pivot data
- Both in Collection "Book of Psalms"
- Left: page 5, order 1
- Right: page 10, order 2

**Expected**:
- Conflict detected for collection pivot
- Resolution uses left's page/order
- UI shows "Conflict on collection: page/order differ"

### 4. Mixed Relationships
**Setup**: Complex relationship merging
- Left: 2 genres, 3 URLs, 1 related music
- Right: 1 genre (different), 2 URLs (1 duplicate), 2 related music (1 same)

**Expected**:
- Genres: 3 total (union)
- URLs: 5 total (all kept, duplicates allowed)
- Related music: 3 total (union, deduplicated)
- No conflicts for relationships

### 5. Empty vs Non-empty
**Setup**: Missing data handling
- Left: subtitle empty, custom_id "ABC"
- Right: subtitle "Subtitle", custom_id empty

**Expected**:
- No conflict for subtitle (left empty, right has value → use right)
- No conflict for custom_id (left has value, right empty → use left)
- UI shows values without conflict badges
- Note: Empty vs non-empty is not considered a conflict, just different values

### 6. Permission Tests
**Setup**: Different user ownership
- User A owns left music
- User B owns right music
- Editor user attempts merge

**Expected**:
- Editor can merge (has permission)
- Regular user cannot merge different owners
- Merge result owned by the user doing the merge (Editor)

### 7. Post-Merge Updates
**Setup**: Merge with existing references
- Right music has music plan slot assignments
- Other records reference right music

**Expected**:
- After merge, assignments updated to reference left music
- Right music deleted
- All references intact

### 8. Error Conditions
**Setup**: Invalid scenarios
- Same music selected on both sides
- One music doesn't exist
- Database transaction fails

**Expected**:
- Validation errors shown
- Transaction rolled back
- User-friendly error messages

## Test Implementation

### Unit Tests
```php
// Tests/Unit/MusicMergeServiceTest.php
test('detects direct field conflicts', function () {
    $left = Music::factory()->create(['title' => 'A']);
    $right = Music::factory()->create(['title' => 'B']);
    
    $merger = new MusicMergeService($left, $right);
    $conflicts = $merger->detectConflicts();
    
    expect($conflicts)->toHaveKey('title');
});

test('merges collections without conflict', function () {
    $collection1 = Collection::factory()->create();
    $collection2 = Collection::factory()->create();
    
    $left = Music::factory()->hasAttached($collection1, ['page_number' => 1])->create();
    $right = Music::factory()->hasAttached($collection2, ['page_number' => 2])->create();
    
    $merger = new MusicMergeService($left, $right);
    $merged = $merger->getMergedCollections();
    
    expect($merged)->toHaveCount(2);
});
```

### Feature Tests
```php
// Tests/Feature/MusicMergerComponentTest.php
test('merge page loads', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    
    Livewire::test(MusicMerger::class)
        ->assertSee('Merge Music');
});

test('compare action shows merged data', function () {
    $left = Music::factory()->create();
    $right = Music::factory()->create();
    
    Livewire::test(MusicMerger::class)
        ->set('leftMusicId', $left->id)
        ->set('rightMusicId', $right->id)
        ->call('compare')
        ->assertSet('showComparison', true)
        ->assertSee($left->title);
});
```

### Browser Tests (if enabled)
- Navigate to merge page
- Select music items
- Click compare
- Verify conflict display
- Edit merged values
- Execute merge
- Verify redirect and updates

## Test Data Factory
```php
// database/factories/MusicFactory.php
public function withRelations()
{
    return $this->afterCreating(function (Music $music) {
        $music->collections()->attach(
            Collection::factory()->create(),
            ['page_number' => rand(1, 100), 'order_number' => rand(1, 50)]
        );
        $music->genres()->attach(Genre::factory()->create());
        $music->urls()->saveMany(MusicUrl::factory()->count(2)->make());
    });
}
```

## Success Criteria
- All tests pass
- No data loss during merge
- Conflict detection accurate
- UI responsive and intuitive
- Performance acceptable with large datasets
- Audit logs created for merge operations