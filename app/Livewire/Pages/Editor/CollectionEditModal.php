<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Collection;
use App\Models\Genre;
use App\Services\CollectionCoverService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class CollectionEditModal extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public bool $show = false;

    public ?int $collectionId = null;

    public string $title = '';

    public ?string $abbreviation = null;

    public ?string $author = null;

    public bool $isPrivate = false;

    public array $selectedGenres = [];

    public bool $canUploadCover = false;

    public ?string $currentCoverUrl = null;

    public string $photoLicense = '';

    #[Validate(['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'])]
    public $photo = null;

    public string $cropAlign = 'top';

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
        $this->canUploadCover = Gate::check('uploadCover', $collection);
        $this->currentCoverUrl = $collection->coverUrl();
        $this->photoLicense = $collection->photo_license ?? '';
        $this->photo = null;
        $this->cropAlign = 'top';
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
        $this->reset(['collectionId', 'title', 'abbreviation', 'author', 'isPrivate', 'selectedGenres', 'canUploadCover', 'currentCoverUrl', 'photo', 'photoLicense', 'cropAlign']);
        $this->dispatch('collection-updated');
    }

    /**
     * Upload a cover image for the collection.
     */
    public function uploadCover(CollectionCoverService $service): void
    {
        $collection = Collection::findOrFail($this->collectionId);
        $this->authorize('uploadCover', $collection);

        $this->validateOnly('photo', [
            'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $service->store($collection, $this->photo, $this->cropAlign);

        $this->photo = null;
        $this->currentCoverUrl = $collection->fresh()->coverUrl();
        $this->dispatch('collection-updated');
    }

    /**
     * Save the photo license string.
     */
    public function savePhotoLicense(): void
    {
        $collection = Collection::findOrFail($this->collectionId);
        $this->authorize('uploadCover', $collection);

        $this->validateOnly('photoLicense', [
            'photoLicense' => ['nullable', 'string', 'max:500'],
        ]);

        $collection->update(['photo_license' => $this->photoLicense ?: null]);
        $this->dispatch('collection-updated');
    }

    /**
     * Delete the collection's cover image.
     */
    public function deleteCover(CollectionCoverService $service): void
    {
        $collection = Collection::findOrFail($this->collectionId);
        $this->authorize('uploadCover', $collection);

        $service->delete($collection);

        $this->currentCoverUrl = null;
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
