<?php

namespace App\Concerns;

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use Illuminate\Support\Facades\Auth;

trait HasMusicSearchScopes
{
    /**
     * Apply search and filter scopes to a music query.
     */
    protected function applyScopes($query, bool $searching)
    {
        $query = $query
            ->visibleTo(Auth::user())
            ->when($this->filter === 'public', fn ($q) => $q->public())
            ->when($this->filter === 'private', fn ($q) => $q->private())
            ->when($this->filter === 'mine', fn ($q) => $q->where('user_id', Auth::id()));

        if ($searching) {
            $words = preg_split('/\s+/', trim($this->search), -1, PREG_SPLIT_NO_EMPTY);
            $query = $query->orWhere(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->where('musics.titles', 'ilike', '%'.$word.'%');
                }
            });
            $query->orderByRaw(
                'GREATEST('.implode(', ', array_fill(0, count($words), 'similarity(musics.titles, ?)')).') DESC',
                $words
            );
        }

        // Collections: keep your existing ilike logic; no full-text index required
        $query = $query
            ->when($this->collectionFilter !== '', function ($q) {
                $q->whereHas('collections', function ($subQuery) {
                    // keep whatever your existing scopeSearch does on Collection (Eloquent scope)
                    $subQuery->search($this->collectionFilter);
                });
            })
            ->when($this->authorFilter !== '', function ($q) {
                $q->whereHas('authors', function ($subQuery) {
                    // keep whatever your existing scopeSearch does on Author (Eloquent scope)
                    $subQuery->search($this->authorFilter);
                });
            })
            ->when($this->collectionFreeText !== '', function ($q) {
                $words = preg_split('/\s+/', trim($this->collectionFreeText));
                $q->whereHas('collections', function ($subQuery) use ($words) {
                    foreach ($words as $word) {
                        $subQuery->where(function ($qq) use ($word) {
                            $qq->where('collections.title', 'ilike', "%{$word}%")
                                ->orWhere('collections.abbreviation', 'ilike', "%{$word}%")
                                ->orWhere('music_collection.order_number', 'ilike', "%{$word}%");
                        });
                    }
                });
            });

        $query = $query
            ->when($this->authorFreeText !== '', function ($q) {
                $authorIds = Author::search($this->authorFreeText)
                    ->take(500)
                    ->keys();

                if ($authorIds->isEmpty()) {
                    $q->whereRaw('1=0'); // AND semantics: no matching author => no musics

                    return;
                }

                $q->whereHas('authors', fn ($aq) => $aq->whereIn('authors.id', $authorIds));
            });

        $query = $query
            ->forCurrentGenre()
            ->with(['genres', 'collections', 'authors'])
            ->withCount('collections')
            ->withCount(['verifications as verified_verifications_count' => function ($q) {
                $q->where('status', 'verified');
            }]);

        // Only order by title when NOT using Scout search (keep relevance rank when searching)
        if (! $searching) {
            $query->orderBy('title');
        }

        return $query;
    }

    /**
     * Get collections for the dropdown filter.
     */
    public function getCollectionsProperty()
    {
        return Collection::visibleTo(Auth::user())
            ->forCurrentGenre()
            ->orderBy('title')
            ->get();
    }

    /**
     * Get authors for the dropdown filter.
     */
    public function getAuthorsProperty()
    {
        return Author::visibleTo(Auth::user())
            ->orderBy('name')
            ->get();
    }
}
