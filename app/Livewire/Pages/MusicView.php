<?php

namespace App\Livewire\Pages;

use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\Score;
use App\Services\MusicPlanScoreListService;
use Illuminate\Support\Collection;
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

        $this->music = $music->load(['collections', 'authors', 'genres', 'urls', 'scriptureReferences', 'directMusicRelations.relatedMusic', 'inverseMusicRelations.music', 'tags']);
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
            'borrowedScores' => $this->borrowedScores(),
        ])->layout('layouts::shell', [
            'title' => $this->music->title,
            'description' => $description,
        ]);
    }

    /**
     * The scores of this music the viewer holds through someone else's live loan,
     * each with the loan-scoped link and incipit it is read through.
     *
     * Resolved by the service list so the music page offers exactly what a plan
     * would; published scores are left to the library section above.
     *
     * @return Collection<int, array{score: Score, url: string|null, incipit_url: string|null, expires_at: string|null}>
     */
    private function borrowedScores(): Collection
    {
        $user = Auth::user();

        if ($user === null) {
            return collect();
        }

        $entries = app(MusicPlanScoreListService::class)
            ->forMusicIds([$this->music->id], $user)
            ->get($this->music->id, collect())
            ->filter(fn (array $entry): bool => $entry['is_borrowed']);

        $scores = Score::query()
            ->with(['user', 'publication'])
            ->findMany($entries->pluck('id'))
            ->reject(fn (Score $score): bool => $score->isPublished())
            ->keyBy('id');

        return $entries
            ->filter(fn (array $entry): bool => $scores->has($entry['id']))
            ->map(fn (array $entry): array => [
                'score' => $scores->get($entry['id']),
                'url' => $entry['url'],
                'incipit_url' => $entry['incipit_url'],
                'expires_at' => $entry['expires_at'],
            ])
            ->values();
    }
}
