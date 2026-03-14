<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use App\Services\AuthorAvatarService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class AuthorEditModal extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public bool $show = false;

    public ?int $authorId = null;

    public string $name = '';

    public bool $isPrivate = false;

    public bool $canChangePrivacy = false;

    public bool $canUploadAvatar = false;

    public ?string $currentAvatarUrl = null;

    public string $photoLicense = '';

    #[Validate(['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'])]
    public $photo = null;

    public string $cropAlign = 'top';

    /**
     * Open the modal for the given author.
     */
    #[On('edit-author')]
    public function open(int $authorId): void
    {
        $author = Author::findOrFail($authorId);
        $this->authorize('update', $author);

        $this->authorId = $author->id;
        $this->name = $author->name;
        $this->isPrivate = $author->is_private;
        $this->canChangePrivacy = Gate::check('changePrivacy', $author);
        $this->canUploadAvatar = Gate::check('uploadAvatar', $author);
        $this->currentAvatarUrl = $author->avatarUrl();
        $this->photoLicense = $author->photo_license ?? '';
        $this->photo = null;
        $this->cropAlign = 'top';
        $this->show = true;
    }

    /**
     * Save changes to the author.
     */
    public function update(): void
    {
        $author = Author::findOrFail($this->authorId);
        $this->authorize('update', $author);

        $validated = $this->validate([
            'name' => $this->getNameValidationRule($author),
            'isPrivate' => ['boolean'],
        ]);

        $author->update([
            ...$validated,
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $this->show = false;
        $this->reset(['authorId', 'name', 'isPrivate', 'canChangePrivacy', 'canUploadAvatar', 'currentAvatarUrl', 'photo', 'photoLicense', 'cropAlign']);
        $this->dispatch('author-updated');
    }

    /**
     * Upload an avatar image for the author.
     */
    public function uploadAvatar(AuthorAvatarService $service): void
    {
        $author = Author::findOrFail($this->authorId);
        $this->authorize('uploadAvatar', $author);

        $this->validateOnly('photo', [
            'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $service->store($author, $this->photo, $this->cropAlign);

        $this->photo = null;
        $this->currentAvatarUrl = $author->fresh()->avatarUrl();
        $this->dispatch('author-updated');
    }

    /**
     * Save the photo license string.
     */
    public function savePhotoLicense(): void
    {
        $author = Author::findOrFail($this->authorId);
        $this->authorize('uploadAvatar', $author);

        $this->validateOnly('photoLicense', [
            'photoLicense' => ['nullable', 'string', 'max:500'],
        ]);

        $author->update(['photo_license' => $this->photoLicense ?: null]);
        $this->dispatch('author-updated');
    }

    /**
     * Delete the author's avatar.
     */
    public function deleteAvatar(AuthorAvatarService $service): void
    {
        $author = Author::findOrFail($this->authorId);
        $this->authorize('uploadAvatar', $author);

        $service->delete($author);

        $this->currentAvatarUrl = null;
        $this->dispatch('author-updated');
    }

    /**
     * Get the appropriate validation rule for the name field.
     */
    private function getNameValidationRule(Author $author): array
    {
        $rules = ['required', 'string', 'max:255'];

        $rules[] = function ($attribute, $value, $fail) use ($author) {
            if ($this->isPrivate === false) {
                $exists = Author::where('name', $value)
                    ->where('is_private', false)
                    ->where('id', '!=', $author->id)
                    ->exists();

                if ($exists) {
                    $fail(__('An author with this name already exists in the public library.'));
                }
            }
        };

        return $rules;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.pages.editor.author-edit-modal');
    }
}
