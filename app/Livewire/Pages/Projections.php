<?php

namespace App\Livewire\Pages;

use App\Models\MusicPlan;
use App\Models\Projection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Projections extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    /**
     * The plan picker: a projection is the scores of one service, so it starts
     * from the plan for that service rather than from nothing.
     */
    public string $planSearch = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Projection::class);
    }

    /**
     * The plans a deck could be built from — the viewer's own, newest first,
     * because a projection is nearly always for the service being prepared.
     *
     * @return Collection<int, MusicPlan>
     */
    #[Computed]
    public function selectablePlans(): Collection
    {
        $search = trim($this->planSearch);

        return MusicPlan::query()
            ->where('user_id', Auth::id())
            ->with('celebration')
            ->when($search !== '', fn (Builder $query) => $query->whereHas(
                'celebration',
                fn (Builder $celebration) => $celebration->where('name', 'ilike', "%{$search}%")
            ))
            ->latest('created_at')
            ->limit(25)
            ->get();
    }

    /**
     * Start a projection from one of those plans.
     */
    public function createFromPlan(int $planId): void
    {
        $this->authorize('create', Projection::class);

        $plan = MusicPlan::query()->findOrFail($planId);
        abort_unless(Gate::allows('view', $plan), 403);

        $projection = Projection::create([
            'user_id' => Auth::id(),
            'music_plan_id' => $plan->getKey(),
            'title' => Projection::titleFor($plan),
        ]);

        $this->redirectRoute('projections.edit', ['projection' => $projection->id], navigate: true);
    }

    /**
     * Start one from nothing — a deck of words and whatever scores are put into
     * it by hand, for a service that was never planned here.
     */
    public function createBlank(): void
    {
        $this->authorize('create', Projection::class);

        $projection = Projection::create([
            'user_id' => Auth::id(),
            'title' => Projection::titleFor(null),
        ]);

        $this->redirectRoute('projections.edit', ['projection' => $projection->id], navigate: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(Projection $projection): void
    {
        $this->authorize('delete', $projection);

        $projection->delete();

        $this->dispatch('toast', message: __('Projection deleted.'), type: 'success');
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', ['title' => __('My Projections')]);
    }

    public function render(): IlluminateView
    {
        $search = trim($this->search);

        $projections = Projection::query()
            ->mine(Auth::user())
            ->withCount('entries')
            ->with('musicPlan.celebration')
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'ilike', "%{$search}%"))
            ->latest('updated_at')
            ->paginate(10);

        return view('livewire.pages.projections', [
            'projections' => $projections,
        ]);
    }
}
