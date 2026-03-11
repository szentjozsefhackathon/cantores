<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class Authors extends Component
{
    use AuthorizesRequests;

    public bool $showCreateModal = false;

    // Form fields for create modal
    public string $name = '';

    public bool $isPrivate = false;

    public function rendering(IlluminateView $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout);
    }

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Author::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('pages.editor.authors');
    }

    /**
     * Show the create modal.
     */
    public function create(): void
    {
        $this->authorize('create', Author::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /**
     * Store a new author.
     */
    public function store(): void
    {
        $this->authorize('create', Author::class);

        $validated = $this->validate([
            'name' => $this->getNameValidationRule(),
            'isPrivate' => ['boolean'],
        ]);

        Author::create([
            ...$validated,
            'user_id' => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $this->showCreateModal = false;
        $this->resetForm();
        $this->dispatch('author-created');
    }

    /**
     * Get the appropriate validation rule for the name field.
     * Only enforces uniqueness for public authors.
     */
    private function getNameValidationRule(): array
    {
        $rules = ['required', 'string', 'max:255'];

        $rules[] = function ($attribute, $value, $fail) {
            if ($this->isPrivate === false) {
                $exists = Author::where('name', $value)
                    ->where('is_private', false)
                    ->exists();

                if ($exists) {
                    $fail(__('An author with this name already exists in the public library.'));
                }
            }
        };

        return $rules;
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->name = '';
        $this->isPrivate = false;
    }
}
