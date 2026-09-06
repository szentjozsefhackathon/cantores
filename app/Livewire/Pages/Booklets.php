<?php

namespace App\Livewire\Pages;

use App\Models\Booklet;
use App\Models\MusicPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Booklets extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    /**
     * The plan picker: a booklet is the scores of one service, so it starts from
     * the plan for that service rather than from nothing.
     */
    public string $planSearch = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Booklet::class);
    }

    /**
     * The plans this booklet could be built from — the viewer's own, newest
     * first, because a booklet is nearly always for the service being prepared.
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
     * Start a booklet from one of those plans.
     */
    public function createFromPlan(int $planId): void
    {
        $this->authorize('create', Booklet::class);

        $plan = MusicPlan::query()->findOrFail($planId);
        abort_unless(Gate::allows('view', $plan), 403);

        $booklet = Booklet::create([
            'user_id' => Auth::id(),
            'music_plan_id' => $plan->getKey(),
            'title' => Booklet::titleFor($plan),
        ]);

        $this->redirectRoute('booklets.edit', ['booklet' => $booklet->id], navigate: true);
    }

    /**
     * A booklet with no service behind it — rarer, but the editor allows scores
     * to be chosen without a plan, so the list allows one to be started that way.
     */
    public function createBlank(): void
    {
        $this->authorize('create', Booklet::class);

        $booklet = Booklet::create([
            'user_id' => Auth::id(),
            'title' => Booklet::titleFor(null),
        ]);

        $this->redirectRoute('booklets.edit', ['booklet' => $booklet->id], navigate: true);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('My Booklets'),
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(Booklet $booklet): void
    {
        $this->authorize('delete', $booklet);

        $booklet->delete();

        $this->dispatch('toast', message: __('Booklet deleted.'), type: 'success');
    }

    public function render(): IlluminateView
    {
        $search = trim($this->search);

        $booklets = Booklet::query()
            ->mine(Auth::user())
            ->withCount('entries')
            ->with('musicPlan.celebration')
            ->when($search !== '', fn ($query) => $query->where('title', 'ilike', "%{$search}%"))
            ->latest('updated_at')
            ->paginate(10);

        return view('livewire.pages.booklets', [
            'booklets' => $booklets,
        ]);
    }
}
