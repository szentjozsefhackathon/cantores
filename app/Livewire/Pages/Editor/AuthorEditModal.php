<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class AuthorEditModal extends Component
{
    use AuthorizesRequests;

    public bool $show = false;

    public ?int $authorId = null;

    public string $name = '';

    public bool $isPrivate = false;

    public bool $canChangePrivacy = false;

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
        $this->reset(['authorId', 'name', 'isPrivate', 'canChangePrivacy']);
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
