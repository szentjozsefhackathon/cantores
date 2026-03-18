<?php

namespace App\Livewire\Pages;

use App\Facades\GenreContext;
use App\Models\Celebration;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MyMusicPlans extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        //
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createMusicPlan(): void
    {
        $musicPlan = \App\Models\MusicPlan::create([
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'is_private' => true,
            'genre_id' => GenreContext::getId(),
        ]);

        $musicPlan->createCustomCelebration('Egyedi ünnep');

        $this->redirectRoute('music-plan-editor', ['musicPlan' => $musicPlan->id], navigate: true);
    }

    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        $this->resetPage();
    }

    public function getCelebrationsQuery()
    {
        $genreId = GenreContext::getId();

        $query = Celebration::query()
            ->whereHas('musicPlans', function ($q) use ($genreId) {
                $q->visibleTo(Auth::user())
                    ->where('user_id', Auth::id());

                if ($genreId !== null) {
                    $q->where(function ($subQ) use ($genreId) {
                        $subQ->whereNull('genre_id')
                            ->orWhere('genre_id', $genreId);
                    });
                }
            })
            ->orderBy('actual_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->orderBy('celebration_key', 'asc');

        $searchTerm = trim($this->search);
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('season_text', 'ilike', "%{$searchTerm}%")
                    ->orWhere('year_letter', 'ilike', "%{$searchTerm}%");
            });
        }

        return $query;
    }

    public function getCelebrationsWithPlans()
    {
        $genreId = GenreContext::getId();

        $celebrations = $this->getCelebrationsQuery()->paginate(10);

        $celebrations->load(['musicPlans' => function ($query) use ($genreId) {
            $query->visibleTo(Auth::user())
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc');

            if ($genreId !== null) {
                $query->where(function ($q) use ($genreId) {
                    $q->whereNull('genre_id')
                        ->orWhere('genre_id', $genreId);
                });
            }
        }]);

        return $celebrations;
    }

    public function render()
    {
        return view('pages.my-music-plans', [
            'celebrations' => $this->getCelebrationsWithPlans(),
        ]);
    }
}
