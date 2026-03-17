<?php

namespace App\Livewire\Pages;

use App\Models\Music;
use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

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

        $authors = $this->music->authors->pluck('name')->join(', ');
        $description = $authors
            ? "Liturgikus zenemű: {$this->music->title} – {$authors}. Részletek, gyűjtemények és kapcsolódó énekek a Cantores.hu Énektárában."
            : "Liturgikus zenemű: {$this->music->title}. Részletek, gyűjtemények és kapcsolódó énekek a Cantores.hu Énektárában.";

        return view('pages.music-view', [
            'musicPlans' => $musicPlans,
        ])->layout('layouts::app.main', [
            'title' => $this->music->title,
            'description' => $description,
        ]);
    }
}
