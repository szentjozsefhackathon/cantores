<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Collection;
use App\Models\Genre;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CollectionEditModal extends Component
{
    use AuthorizesRequests;

    public bool $show = false;

    public ?int $collectionId = null;

    public string $title = '';

    public ?string $abbreviation = null;

    public ?string $author = null;

    public bool $isPrivate = false;

    public array $selectedGenres = [];

    /**
     * Open the modal for the given collection.
     */
    #[On('edit-collection')]
    public function open(int $collectionId): void
    {
        $collection = Collection::findOrFail($collectionId);
        $this->authorize('update', $collection);

        $this->collectionId = $collection->id;
        $this->title = $collection->title;
        $this->abbreviation = $collection->abbreviation;
        $this->author = $collection->author;
        $this->isPrivate = $collection->is_private;
        $this->selectedGenres = $collection->genres->pluck('id')->toArray();
        $this->show = true;
    }

    /**
     * Save changes to the collection.
     */
    public function update(): void
    {
        $collection = Collection::findOrFail($this->collectionId);
        $this->authorize('update', $collection);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('collections', 'title')->ignore($collection->id)],
            'abbreviation' => ['nullable', 'string', 'max:20', Rule::unique('collections', 'abbreviation')->ignore($collection->id)],
            'author' => ['nullable', 'string', 'max:255'],
            'isPrivate' => ['boolean'],
            'selectedGenres' => ['nullable', 'array'],
            'selectedGenres.*' => ['integer', Rule::exists('genres', 'id')],
        ]);

        $collection->update([
            ...$validated,
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $collection->genres()->sync($validated['selectedGenres'] ?? []);

        $this->show = false;
        $this->reset(['collectionId', 'title', 'abbreviation', 'author', 'isPrivate', 'selectedGenres']);
        $this->dispatch('collection-updated');
    }

    /**
     * Get all genres for selection.
     */
    public function genres(): \Illuminate\Support\Collection
    {
        return Genre::allCached();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.pages.editor.collection-edit-modal');
    }
}
