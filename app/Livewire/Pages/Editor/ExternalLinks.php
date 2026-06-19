<?php

namespace App\Livewire\Pages\Editor;

use App\Models\ExternalLink;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ExternalLinks extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string|max:2000')]
    public string $description = '';

    #[Validate('required|url|max:2000')]
    public string $url = '';

    #[Validate('integer|min:0')]
    public int $sortOrder = 0;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', ExternalLink::class);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('pages.editor.external-links', [
            'links' => ExternalLink::query()->ordered()->get(),
        ])->layout('layouts::app');
    }

    /**
     * Show the create modal.
     */
    public function create(): void
    {
        $this->authorize('create', ExternalLink::class);
        $this->resetForm();
        $this->showModal = true;
    }

    /**
     * Show the edit modal for an existing link.
     */
    public function edit(ExternalLink $externalLink): void
    {
        $this->authorize('update', $externalLink);

        $this->editingId = $externalLink->id;
        $this->title = $externalLink->title;
        $this->description = $externalLink->description;
        $this->url = $externalLink->url;
        $this->sortOrder = $externalLink->sort_order;
        $this->showModal = true;
    }

    /**
     * Store or update the external link.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'url' => $validated['url'],
            'sort_order' => $validated['sortOrder'],
        ];

        if ($this->editingId !== null) {
            $link = ExternalLink::findOrFail($this->editingId);
            $this->authorize('update', $link);
            $link->update($attributes);
            $message = __('External link updated.');
        } else {
            $this->authorize('create', ExternalLink::class);
            ExternalLink::create($attributes);
            $message = __('External link created.');
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', message: $message, type: 'success');
    }

    /**
     * Delete the external link.
     */
    public function delete(ExternalLink $externalLink): void
    {
        $this->authorize('delete', $externalLink);
        $externalLink->delete();

        $this->dispatch('toast', message: __('External link deleted.'), type: 'success');
    }

    /**
     * Reset form fields.
     */
    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->description = '';
        $this->url = '';
        $this->sortOrder = 0;
    }
}
