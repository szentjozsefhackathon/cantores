<?php

namespace App\Livewire\Pages;

use App\Models\Booklet;
use App\Models\MusicPlan;
use App\Models\Presentation;
use App\Models\Projection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything a music plan has been turned into, one service to a row.
 *
 * Booklets and projections used to have a list each, and that was exactly the
 * wrong cut: a parish preparing one Sunday has a booklet for the band, a second
 * one for the cantor and a 16:9 deck for the screen, and two alphabetical lists
 * of look-alike titles gave no way to tell which deck belonged with which
 * booklet. So the service is the row, and its documents sit inside it.
 */
class PlanDocuments extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    /**
     * The celebration being looked for in the new-document picker.
     */
    public string $planSearch = '';

    /**
     * Which of the two the picker is about to start: 'booklet' or 'projection'.
     */
    public string $newType = 'booklet';

    public function mount(): void
    {
        $this->authorize('viewAny', Booklet::class);
    }

    /**
     * The plans a document could be built from — the viewer's own, newest
     * first, because a document is nearly always for the service being prepared.
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
     * The documents started without a plan behind them.
     *
     * Rare, and they have no service to be grouped under, so they are shown once
     * beneath the grouped rows rather than paginated alongside them.
     *
     * @return array{booklets: Collection<int, Booklet>, projections: Collection<int, Projection>}
     */
    #[Computed]
    public function planless(): array
    {
        $search = trim($this->search);

        return [
            'booklets' => Booklet::query()
                ->mine(Auth::user())
                ->whereNull('music_plan_id')
                ->withCount('entries')
                ->when($search !== '', fn (Builder $query) => $query->where('title', 'ilike', "%{$search}%"))
                ->latest('updated_at')
                ->get(),
            'projections' => Projection::query()
                ->mine(Auth::user())
                ->whereNull('music_plan_id')
                ->withCount('entries')
                ->when($search !== '', fn (Builder $query) => $query->where('title', 'ilike', "%{$search}%"))
                ->latest('updated_at')
                ->get(),
        ];
    }

    /**
     * This person's show, if one is up — so the same page that offers to
     * bootstrap tomorrow's decks also says plainly whether today's is still up.
     */
    #[Computed]
    public function currentPresentation(): ?Presentation
    {
        return Presentation::currentFor(Auth::user())?->load('projection');
    }

    /**
     * The decks put up lately, so yesterday's is one tap from going back up.
     *
     * @return EloquentCollection<int, Projection>
     */
    #[Computed]
    public function recentProjections(): EloquentCollection
    {
        return Presentation::recentFor(Auth::user());
    }

    public function setNewType(string $type): void
    {
        $this->newType = $type === 'projection' ? 'projection' : 'booklet';
    }

    /**
     * Catch up with a deck sent to the wall from one of this page's own rows.
     *
     * `send-to-screen` is a child component, so its own re-render never
     * touches the "currently projecting" banner above it; this listener is
     * what keeps that banner in step with the button a row below just pressed.
     */
    #[On('presentation-changed')]
    public function refreshPresentation(): void
    {
        unset($this->currentPresentation, $this->recentProjections);
    }

    /**
     * Take the show down, on every screen at once.
     *
     * The fast way out of a deck started by mistake — the wrong aspect ratio,
     * say — from the one page a cantor is looking at when they notice, rather
     * than a trip to the remote or the wall itself. With nothing up any more,
     * the next deck put up opens on the title card, the same way a screen
     * opened fresh would.
     */
    public function removeFromScreen(): void
    {
        if (! $this->currentPresentation instanceof Presentation) {
            return;
        }

        Presentation::takeDownFor(Auth::user());

        unset($this->currentPresentation);

        $this->dispatch('toast', message: __('Removed from the screen.'), type: 'success');
    }

    /**
     * Put a recently shown deck back up.
     */
    public function putUp(int $projectionId): void
    {
        $projection = Projection::query()->findOrFail($projectionId);

        $this->authorize('view', $projection);

        Presentation::putUp(Auth::user(), $projection);

        unset($this->currentPresentation, $this->recentProjections);
    }

    /**
     * Start a booklet or a projection — whichever the picker was opened for —
     * from one of those plans.
     */
    public function createFromPlan(int $planId): void
    {
        $plan = MusicPlan::query()->findOrFail($planId);
        abort_unless(Gate::allows('view', $plan), 403);

        if ($this->newType === 'projection') {
            $this->authorize('create', Projection::class);

            $projection = Projection::create([
                'user_id' => Auth::id(),
                'music_plan_id' => $plan->getKey(),
                'title' => Projection::titleFor($plan),
            ]);

            $this->redirectRoute('projections.edit', ['projection' => $projection->id], navigate: true);

            return;
        }

        $this->authorize('create', Booklet::class);

        $booklet = Booklet::create([
            'user_id' => Auth::id(),
            'music_plan_id' => $plan->getKey(),
            'title' => Booklet::titleFor($plan),
        ]);

        $this->redirectRoute('booklets.edit', ['booklet' => $booklet->id], navigate: true);
    }

    /**
     * A document with no service behind it — rarer, but both editors allow
     * scores to be chosen without a plan.
     */
    public function createBlank(): void
    {
        if ($this->newType === 'projection') {
            $this->authorize('create', Projection::class);

            $projection = Projection::create([
                'user_id' => Auth::id(),
                'title' => Projection::titleFor(null),
            ]);

            $this->redirectRoute('projections.edit', ['projection' => $projection->id], navigate: true);

            return;
        }

        $this->authorize('create', Booklet::class);

        $booklet = Booklet::create([
            'user_id' => Auth::id(),
            'title' => Booklet::titleFor(null),
        ]);

        $this->redirectRoute('booklets.edit', ['booklet' => $booklet->id], navigate: true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('Booklets & Projections'),
        ]);
    }

    /**
     * Every service this person has a plan for, most recently worked on
     * first — with each service's own booklets and decks loaded beside it, so
     * which deck goes with which booklet is a matter of reading one row.
     *
     * A plan with nothing built from it yet still gets a row, its two columns
     * empty and waiting: the whole point of putting the create buttons inside
     * those columns is that the plan has to already be there for a button
     * inside it to make sense.
     *
     * @return LengthAwarePaginator<int, MusicPlan>
     */
    protected function plans(): LengthAwarePaginator
    {
        $userId = Auth::id();
        $search = trim($this->search);

        $matchesDocuments = fn (Builder $query) => $query->where('user_id', $userId)
            ->where('title', 'ilike', "%{$search}%");

        return MusicPlan::query()
            ->where('user_id', $userId)
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->whereHas('celebration', fn (Builder $celebration) => $celebration->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('booklets', $matchesDocuments)
                    ->orWhereHas('projections', $matchesDocuments)
            ))
            ->with([
                'celebration',
                'booklets' => fn ($query) => $query->where('user_id', $userId)->withCount('entries')->latest('updated_at'),
                'projections' => fn ($query) => $query->where('user_id', $userId)->withCount('entries')->latest('updated_at'),
            ])
            ->orderByRaw(
                'greatest('
                .'coalesce((select max(updated_at) from booklets where booklets.music_plan_id = music_plans.id and booklets.user_id = ?), music_plans.updated_at), '
                .'coalesce((select max(updated_at) from projections where projections.music_plan_id = music_plans.id and projections.user_id = ?), music_plans.updated_at)'
                .') desc',
                [$userId, $userId]
            )
            ->paginate(10);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.plan-documents', [
            'plans' => $this->plans(),
        ]);
    }
}
