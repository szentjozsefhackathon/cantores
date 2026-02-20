<?php

namespace App\Livewire\Pages;

use App\Models\Music;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app.main')]
class MusicView extends Component
{
    public Music $music;

    public function mount($music): void
    {
        // Load existing music
        if (! $music instanceof Music) {
            $music = Music::visibleTo(Auth::user())->findOrFail($music);
        }

        // Check authorization using Gate (supports guest users)
        if (! Gate::allows('view', $music)) {
            abort(403);
        }

        $this->music = $music->load(['collections', 'authors', 'genres', 'urls', 'relatedMusic']);
    }

    public function render()
    {
        return view('livewire.pages.music-view');
    }
}
