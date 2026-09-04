<?php

namespace App\Livewire\Pages;

use App\Enums\ScoreFormat;
use App\Enums\ScoreLicense;
use App\Models\Collection;
use App\Models\Genre;
use App\Models\Score;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public library index.
 *
 * Runs its own plain query rather than going through HasMusicSearchScopes: that
 * one unwraps Scout's where-group to OR in an ILIKE fallback and ranks by
 * pg_trgm similarity, which is delicate and aimed at the whole music catalogue.
 * A published library is small and its filters are exact, so a direct query is
 * both simpler and easier to keep correct.
 */
class PublicScores extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $license = '';

    #[Url(except: '')]
    public string $format = '';

    #[Url(except: '')]
    public ?int $collection = null;

    #[Url(except: '')]
    public ?int $genre = null;

    public function updating(string $field): void
    {
        if (in_array($field, ['search', 'license', 'format', 'collection', 'genre'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'license', 'format', 'collection', 'genre']);
        $this->resetPage();
    }

    /**
     * Published scores eligible for the library, grouped by the music they
     * belong to so a piece with several free scores appears as one card. A
     * score with no linked music groups alone, keyed by its own id.
     *
     * Fetched whole and grouped in PHP rather than paginated at the database:
     * the published library is small (see the class docblock above), and a
     * database-level LIMIT would risk splitting one music's scores across two
     * pages.
     *
     * @return LengthAwarePaginator<int, \Illuminate\Support\Collection<int, Score>>
     */
    private function scoreGroups(): LengthAwarePaginator
    {
        $search = trim($this->search);

        $scores = Score::query()
            ->published()
            ->with(['music.authors', 'music.collections', 'publication', 'files', 'user'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('scores.title', 'ilike', "%{$search}%")
                        ->orWhereHas('music', fn (Builder $music) => $music->where('title', 'ilike', "%{$search}%"));
                });
            })
            ->when($this->license !== '', fn (Builder $query) => $query->whereHas(
                'publication',
                fn (Builder $publication) => $publication
                    ->where('license', $this->license)
                    ->orWhere('outbound_license', $this->license)
            ))
            ->when($this->format !== '', fn (Builder $query) => $query->where('scores.format', $this->format))
            ->when($this->collection !== null, fn (Builder $query) => $query->whereHas(
                'music.collections',
                fn (Builder $collections) => $collections->where('collections.id', $this->collection)
            ))
            ->when($this->genre !== null, fn (Builder $query) => $query->whereHas(
                'music.genres',
                fn (Builder $genres) => $genres->where('genres.id', $this->genre)
            ))
            ->orderByDesc('scores.updated_at')
            ->get();

        $groups = $scores
            ->groupBy(fn (Score $score) => $score->music_id !== null ? "music-{$score->music_id}" : "score-{$score->id}")
            ->values();

        $page = Paginator::resolveCurrentPage();
        $perPage = 24;

        return new Paginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    public function rendering(IlluminateView $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';

        $view->layout($layout, [
            'title' => __('Free sheet music'),
            'description' => __('Freely downloadable sheet music — public domain and Creative Commons scores for cantors and church musicians.'),
            'canonical' => route('public-scores'),
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.public-scores', [
            'groups' => $this->scoreGroups(),
            'licenses' => ScoreLicense::cases(),
            'formats' => ScoreFormat::cases(),
            'collections' => Collection::query()->public()->orderBy('title')->get(['id', 'title', 'abbreviation']),
            'genres' => Genre::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
