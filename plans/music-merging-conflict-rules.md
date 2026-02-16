# Conflict Detection and Resolution Rules

## Direct Fields
| Field | Conflict Condition | Resolution | UI Indicator |
|-------|-------------------|------------|--------------|
| `title` | Values differ | Use left value, mark conflict | "Conflict on field: Title" badge |
| `subtitle` | One has value, other has different/none | Use left value, mark conflict | Conflict badge |
| `custom_id` | Values differ | Use left value, mark conflict | Conflict badge |
| `is_private` | Values differ (true vs false) | Use FALSE (public), mark conflict | Warning icon |
| `user_id` | After merge, owner changes to user doing the merge | Use current user's ID | N/A |

## Collections (Many-to-Many with Pivot)
### Conflict Detection
- **Same collection ID** with different `page_number` or `order_number` → CONFLICT
- **Different collection IDs** → NO CONFLICT (include both)

### Resolution
1. For conflicting collections (same ID):
   - Use left's `page_number` and `order_number`
   - Mark as conflict in UI
2. For non-conflicting collections:
   - Include all collections from both sides
   - Preserve pivot data from respective source

### Example
```
Left: Collection A (page 5, order 1)
Right: Collection A (page 10, order 2)
→ Conflict: Use left's page 5, order 1
→ Display: "Conflict on collection A: page/order differ"

Left: Collection A
Right: Collection B
→ No conflict: Include both A and B
```

## Genres (Many-to-Many)
- **No conflicts** - union of both sets
- Resolution: Attach all genres from both sides (deduplicated)

## URLs (One-to-Many)
- **No conflicts** - combine all URLs
- Resolution: Create copies of right's URLs attached to left music
- Note: URL labels may duplicate; keep both

## Related Music (Self-referential Many-to-Many)
- **No conflicts** - union of both relationship sets
- Resolution: Attach all related music from both sides
- Ensure no circular references created

## Music Plan Slot Assignments
- Not part of comparison display
- During merge: Update all assignments referencing right music to reference left music
- No conflicts to display

## Conflict UI Design
### Visual Indicators
- **Conflict badge**: Red "Conflict" badge next to field
- **Side-by-side values**: Show left vs right values
- **Resolution preview**: Show which value will be used (left highlighted)
- **Editable override**: Allow user to change merged value despite conflict

### Conflict Summary Panel
- Count of total conflicts
- List of conflicted fields with quick navigation
- Option to "Use right value instead" per field

## Special Cases
### Empty vs Non-empty
- Left has value, right empty → No conflict (use left)
- Left empty, right has value → No conflict (use right)
- Both empty → No conflict

### Privacy Conflict
- Left private, right public → Conflict (use FALSE/public)
- Important: Merged music becomes public regardless of origin

### User Ownership
- After merge, the owner changes to the user doing the merge
- No override functionality needed

## Implementation Logic
```php
protected function detectConflicts(Music $left, Music $right): array
{
    $conflicts = [];
    
    // Direct fields
    foreach (['title', 'subtitle', 'custom_id', 'is_private'] as $field) {
        if ($this->valuesDiffer($left->$field, $right->$field)) {
            $conflicts[$field] = [
                'left' => $left->$field,
                'right' => $right->$field,
                'resolution' => 'left', // default
            ];
        }
    }
    
    // Collections
    foreach ($left->collections as $leftCollection) {
        foreach ($right->collections as $rightCollection) {
            if ($leftCollection->id === $rightCollection->id) {
                if ($leftCollection->pivot->page_number != $rightCollection->pivot->page_number ||
                    $leftCollection->pivot->order_number != $rightCollection->pivot->order_number) {
                    $conflicts['collection_' . $leftCollection->id] = [
                        'type' => 'collection',
                        'collection' => $leftCollection,
                        'left_pivot' => $leftCollection->pivot,
                        'right_pivot' => $rightCollection->pivot,
                        'resolution' => 'left',
                    ];
                }
            }
        }
    }
    
    return $conflicts;
}