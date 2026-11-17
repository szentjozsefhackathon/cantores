<?php

namespace App\Livewire\Pages;

use App\Models\Season;
use App\Models\SeasonEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A leader's seasons — "Ferences Schola 2026-2027" — newest first.
 */
class Seasons extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Season::class);
    }

    /**
     * @return Collection<int, Season>
     */
    #[Computed]
    public function seasons(): Collection
    {
        return Season::query()
            ->mine(Auth::user())
            ->withCount(['events', 'members'])
            ->with(['loans' => fn ($query) => $query->live()])
            ->latest('created_at')
            ->get();
    }

    /**
     * The next date of each season, keyed by season id, in one query.
     *
     * @return \Illuminate\Support\Collection<int, SeasonEvent>
     */
    #[Computed]
    public function nextEvents(): \Illuminate\Support\Collection
    {
        return SeasonEvent::query()
            ->whereIn('season_id', $this->seasons->modelKeys())
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('time')
            ->get()
            ->unique('season_id')
            ->keyBy('season_id');
    }

    public function create(): void
    {
        $this->authorize('create', Season::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $season = Season::create([
            'user_id' => Auth::id(),
            'name' => trim($validated['name']),
        ]);

        $this->redirectRoute('seasons.edit', ['season' => $season->id], navigate: true);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('Season planner'),
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.seasons');
    }
}
