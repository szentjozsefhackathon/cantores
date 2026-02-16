# Music Merging Implementation Todo

## Phase 1: Component Setup
- [ ] Create Livewire component: `php artisan make:livewire Editor/MusicMerger --mfc`
- [ ] Create route in `routes/web.php` for `/editor/musics/merge`
- [ ] Add navigation link in editor sidebar
- [ ] Set up component properties and basic methods

## Phase 2: Music Selection UI
- [ ] Implement left music search/select dropdown with live search
- [ ] Implement right music search/select dropdown
- [ ] Add validation to prevent selecting same music on both sides
- [ ] Add "Compare" button that triggers comparison

## Phase 3: Comparison Logic
- [ ] Load both music models with all relationships
- [ ] Implement field-by-field comparison:
  - Direct attributes (title, subtitle, custom_id, is_private)
  - Collections (with pivot comparison)
  - Genres
  - URLs
  - Related music
- [ ] Detect conflicts based on rules:
  - Same field, different values = conflict
  - Same collection, different page/order = conflict
- [ ] Generate merged data structure with conflict flags

## Phase 4: Comparison Display UI
- [ ] Create three-column layout for left/right/merged
- [ ] Display side-by-side values for each field
- [ ] Highlight conflicts with visual indicators
- [ ] Make merged fields editable (inputs, selects, etc.)
- [ ] Show relationship tables (collections, genres, URLs)

## Phase 5: Merge Execution
- [ ] Implement `saveMerge()` method with database transaction
- [ ] Update left music with merged direct attributes
- [ ] Handle collections: merge, resolve conflicts, attach
- [ ] Handle genres: attach union
- [ ] Handle URLs: create copies for left music
- [ ] Handle related music: update relationships
- [ ] Update music plan slot assignments from right to left
- [ ] Delete right music record
- [ ] Add audit logging for merge operation

## Phase 6: Post-Merge Actions
- [ ] Redirect to left music editor page
- [ ] Show success message with merge summary
- [ ] Ensure all references are properly updated

## Phase 7: Testing & Validation
- [ ] Write Pest tests for merge scenarios
- [ ] Test conflict resolution rules
- [ ] Test permission checks (user must own both or be editor)
- [ ] Test edge cases (private vs public, empty fields)

## Phase 8: Polish & Documentation
- [ ] Add loading states during comparison
- [ ] Add confirmation modal before merge
- [ ] Implement responsive design for mobile
- [ ] Add help text explaining merge rules
- [ ] Update user documentation

## Technical Notes
- Use `wire:model.live` for search inputs
- Use Flux UI components for consistency
- Follow existing patterns from `Musics.php` component
- Ensure proper authorization with policies
- Use eager loading for performance