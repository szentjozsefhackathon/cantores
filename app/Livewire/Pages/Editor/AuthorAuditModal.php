<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Author;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class AuthorAuditModal extends Component
{
    use AuthorizesRequests;

    public bool $show = false;

    public ?int $authorId = null;

    /**
     * Open the modal for the given author.
     */
    #[On('show-author-audit-log')]
    public function open(int $authorId): void
    {
        $author = Author::findOrFail($authorId);
        $this->authorize('view', $author);

        $this->authorId = $author->id;
        $this->show = true;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $author = $this->authorId ? Author::find($this->authorId) : null;

        $audits = $author
            ? $author->audits()->with(['user.city', 'user.firstName'])->latest()->get()
            : collect();

        return view('livewire.pages.editor.author-audit-modal', [
            'author' => $author,
            'audits' => $audits,
        ]);
    }
}
