<?php

namespace App\Livewire\Pages;

use App\Models\Music;
use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app.main')]
class MusicView extends Component
{
    use WithPagination;

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

        $this->music = $music->load(['collections', 'authors', 'genres', 'urls', 'directMusicRelations.relatedMusic', 'inverseMusicRelations.music', 'tags']);
    }

    public function render()
    {
        $musicPlans = MusicPlan::query()
            ->visibleTo(Auth::user())
            ->whereHas('musicAssignments', fn ($q) => $q->where('music_id', $this->music->id))
            ->with(['celebration', 'user'])
            ->paginate(12);

        return view('pages.music-view', [
            'musicPlans' => $musicPlans,
        ]);
    }
}
