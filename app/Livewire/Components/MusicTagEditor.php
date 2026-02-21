<?php

namespace App\Livewire\Components;

use App\Enums\MusicTagType;
use App\Models\MusicTag;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MusicTagEditor extends Component
{
    use AuthorizesRequests;

    public string $newTagName = '';

    public string $newTagType = '';

    public bool $showForm = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('create', MusicTag::class);
    }

    /**
     * Get all tag types for selection.
     */
    public function tagTypes()
    {
        return MusicTagType::cases();
    }

    /**
     * Toggle the form visibility.
     */
    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
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
        $this->showForm = false;

        $this->dispatch('tag-created');
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('components.music-tag-editor', [
            'tagTypes' => $this->tagTypes(),
        ]);
    }
}
