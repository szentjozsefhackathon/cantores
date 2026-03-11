<?php

namespace App\Livewire\Pages\Editor;

use App\Facades\GenreContext;
use App\Models\Music;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\On;
use Livewire\Component;

class Musics extends Component
{
    use AuthorizesRequests;

    public bool $showCreateModal = false;

    // Form fields for create modal
    public string $title = '';

    public ?string $subtitle = null;

    public ?string $customId = null;

    public bool $isPrivate = false;

    public function rendering(IlluminateView $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';
        $view->layout($layout);
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Music::class);
    }

    public function render(): View
    {
        return view('pages.editor.musics');
    }

    #[On('open-create-music-modal')]
    public function create(): void
    {
        $this->authorize('create', Music::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function store(): void
    {
        $this->authorize('create', Music::class);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'customId' => ['nullable', 'string', 'max:255'],
            'isPrivate' => ['boolean'],
        ]);

        $music = Music::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'],
            'custom_id' => $validated['customId'],
            'user_id' => Auth::id(),
            'is_private' => $validated['isPrivate'] ?? false,
        ]);

        $genreId = GenreContext::getId();
        if ($genreId) {
            $music->genres()->attach($genreId);
        }

        $this->showCreateModal = false;
        $this->resetForm();
        $this->redirectRoute('music-editor', ['music' => $music->id]);
    }

    private function resetForm(): void
    {
        $this->title = '';
        $this->subtitle = null;
        $this->customId = null;
        $this->isPrivate = false;
    }
}
