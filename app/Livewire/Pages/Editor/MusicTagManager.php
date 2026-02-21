<?php

namespace App\Livewire\Pages\Editor;

use App\Enums\MusicTagType;
use App\Models\MusicTag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MusicTagManager extends Component
{
    use AuthorizesRequests;

    public string $newTagName = '';

    public string $newTagType = '';

    public ?int $editingTagId = null;

    public string $editingTagName = '';

    public string $editingTagType = '';

    public bool $showDeleteConfirm = false;

    public ?int $deleteTagId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', MusicTag::class);
    }

    /**
     * Get all music tags.
     */
    public function tags()
    {
        return MusicTag::orderBy('type')->orderBy('name')->get();
    }

    /**
     * Get all tag types for selection.
     */
    public function tagTypes()
    {
        return MusicTagType::cases();
    }

    /**
     * Create a new music tag.
     */
    public function createTag(): void
    {
        $this->authorize('create', MusicTag::class);

        $validated = $this->validate([
            'newTagName' => ['required', 'string', 'max:255'],
            'newTagType' => ['required', 'string', Rule::enum(MusicTagType::class)],
        ]);

        // Check if tag already exists
        if (MusicTag::where('name', $validated['newTagName'])
            ->where('type', $validated['newTagType'])
            ->exists()) {
            $this->dispatch('error', __('This tag already exists.'));

            return;
        }

        MusicTag::create([
            'name' => $validated['newTagName'],
            'type' => $validated['newTagType'],
        ]);

        $this->newTagName = '';
        $this->newTagType = '';

        $this->dispatch('tag-created');
    }

    /**
     * Edit a music tag.
     */
    public function editTag(int $tagId): void
    {
        $tag = MusicTag::find($tagId);

        if (! $tag) {
            return;
        }

        $this->authorize('update', $tag);

        $this->editingTagId = $tagId;
        $this->editingTagName = $tag->name;
        $this->editingTagType = $tag->type->value;
    }

    /**
     * Update the editing tag.
     */
    public function updateTag(): void
    {
        $tag = MusicTag::find($this->editingTagId);

        if (! $tag) {
            $this->cancelEditTag();

            return;
        }

        $this->authorize('update', $tag);

        $validated = $this->validate([
            'editingTagName' => ['required', 'string', 'max:255'],
            'editingTagType' => ['required', 'string', Rule::enum(MusicTagType::class)],
        ]);

        // Check if another tag with same name and type exists
        if (MusicTag::where('name', $validated['editingTagName'])
            ->where('type', $validated['editingTagType'])
            ->where('id', '!=', $this->editingTagId)
            ->exists()) {
            $this->dispatch('error', __('This tag already exists.'));

            return;
        }

        $tag->update([
            'name' => $validated['editingTagName'],
            'type' => $validated['editingTagType'],
        ]);

        $this->cancelEditTag();

        $this->dispatch('tag-updated');
    }

    /**
     * Cancel editing a tag.
     */
    public function cancelEditTag(): void
    {
        $this->editingTagId = null;
        $this->editingTagName = '';
        $this->editingTagType = '';
    }

    /**
     * Confirm deletion of a tag.
     */
    public function confirmDeleteTag(int $tagId): void
    {
        $tag = MusicTag::find($tagId);

        if (! $tag) {
            return;
        }

        $this->authorize('delete', $tag);

        $this->deleteTagId = $tagId;
        $this->showDeleteConfirm = true;
    }

    /**
     * Delete a music tag.
     */
    public function deleteTag(): void
    {
        $tag = MusicTag::find($this->deleteTagId);

        if (! $tag) {
            $this->showDeleteConfirm = false;

            return;
        }

        $this->authorize('delete', $tag);

        $tag->delete();

        $this->showDeleteConfirm = false;
        $this->deleteTagId = null;

        $this->dispatch('tag-deleted');
    }

    /**
     * Cancel deletion.
     */
    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
        $this->deleteTagId = null;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('pages.editor.music-tag-manager', [
            'tags' => $this->tags(),
            'tagTypes' => $this->tagTypes(),
        ]);
    }
}
