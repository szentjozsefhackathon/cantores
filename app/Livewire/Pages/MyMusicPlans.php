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

    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        $this->resetPage();
    }

    public function getCelebrationsQuery()
    {
        $query = Celebration::query()
            ->whereHas('musicPlans', function ($q) {
                $q->visibleTo(Auth::user())
                    ->where('user_id', Auth::id());
            })
            ->orderBy('actual_date', 'desc')
            ->orderBy('celebration_key', 'asc');

        // Filter by current genre
        $genreId = GenreContext::getId();
        if ($genreId !== null) {
            $query->whereHas('musicPlans', function ($q) use ($genreId) {
                $q->where(function ($subQ) use ($genreId) {
                    $subQ->whereNull('genre_id')
                        ->orWhere('genre_id', $genreId);
                });
            });
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('season_text', 'ilike', "%{$this->search}%")
                    ->orWhere('year_letter', 'ilike', "%{$this->search}%");
            });
        }

        return $query;
    }

    public function getCelebrationsWithPlans()
    {
        $celebrations = $this->getCelebrationsQuery()->get();

        // Eager load musicPlans with necessary constraints
        $celebrations->load(['musicPlans' => function ($query) {
            $query->visibleTo(Auth::user())
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc');

            // Apply genre filter if needed
            $genreId = GenreContext::getId();
            if ($genreId !== null) {
                $query->where(function ($q) use ($genreId) {
                    $q->whereNull('genre_id')
                        ->orWhere('genre_id', $genreId);
                });
            }
        }]);

        // Filter out celebrations that have no music plans after filtering
        return $celebrations->filter(function ($celebration) {
            return $celebration->musicPlans->isNotEmpty();
        });
    }

    public function render()
    {
        return view('pages.my-music-plans', [
            'celebrations' => $this->getCelebrationsWithPlans(),
        ]);
    }
}
