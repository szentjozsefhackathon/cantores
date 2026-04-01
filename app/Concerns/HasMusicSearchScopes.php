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
        if ($searching) {
            $words = preg_split('/\s+/', trim($this->search), -1, PREG_SPLIT_NO_EMPTY);
            $searching = ! empty($words);
        }

        if ($searching) {

            // Scout has already added its conditions as a grouped where() on $query.
            // We need to OR the ilike fallback into that same group, then AND the
            // constraining scopes outside. We do this by lifting Scout's existing
            // wheres into a new outer group that also contains the ilike fallback.
            $existingWheres = $query->getQuery()->wheres;
            $existingBindings = $query->getQuery()->bindings['where'] ?? [];
            $query->getQuery()->wheres = [];
            $query->getQuery()->bindings['where'] = [];

            $query->where(function ($inner) use ($existingWheres, $existingBindings, $words) {
                $inner->getQuery()->wheres = $existingWheres;
                $inner->getQuery()->bindings['where'] = $existingBindings;
                $inner->orWhere(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $q->Where('musics.titles', 'ilike', '%'.$word.'%');
                    }
                });
            });

            // Improved relevance ranking: use word_similarity for the entire search phrase
            // This gives better results than GREATEST(similarity(...)) because it considers
            // how well the entire phrase matches, not just individual words
            $query->orderByRaw(
                'word_similarity(musics.titles, ?) DESC',
                [$this->search]
            );
        }

        // Apply visibility scope
        $query = $query->visibleTo(Auth::user());

        // Apply own musics filter if enabled
        if (property_exists($this, 'filterOwnMusics') && $this->filterOwnMusics) {
            $query = $query->where('user_id', Auth::id());
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
                $words = preg_split('/(?<=\d)(?=\p{L})|(?<=\p{L})(?=\d)|\s+/u', trim($this->collectionFreeText), -1, PREG_SPLIT_NO_EMPTY);
                $q->whereHas('collections', function ($subQuery) use ($words) {
                    foreach ($words as $word) {
                        $subQuery->where(function ($qq) use ($word) {
                            $qq->where('collections.title', 'ilike', "%{$word}%")
                                ->orWhere('collections.abbreviation', 'ilike', "%{$word}%");

                            if (ctype_digit($word)) {
                                $qq->orWhereRaw('music_collection.order_number ~* ?', ["^{$word}([^0-9]|$)"]);
                            } else {
                                $qq->orWhere('music_collection.order_number', 'ilike', "%{$word}%");
                            }
                        });
                    }
                });
            });

        $query = $query
            ->when($this->authorFreeText !== '', function ($q) {
                $q->whereHas('authors', fn ($aq) => $aq->where('name', 'ilike', '%'.$this->authorFreeText.'%'));
            });

        // Tags: AND logic - music must have ALL selected tags
        $query = $query
            ->when(! empty($this->tagFilters), function ($q) {
                foreach ($this->tagFilters as $tagId) {
                    $q->whereHas('tags', function ($subQuery) use ($tagId) {
                        $subQuery->where('music_tags.id', $tagId);
                    });
                }
            });

        $query = $query
            ->forCurrentGenre()
            ->select(['id', 'title', 'subtitle', 'custom_id', 'user_id', 'is_private'])
            ->with(['genres', 'collections', 'authors', 'tags', 'urls'])
            ->withCount('collections')
            ->withCount(['verifications as verified_verifications_count' => function ($q) {
                $q->where('status', 'verified');
            }]);

        // Only order by title when NOT using Scout search (keep relevance rank when searching)
        if (! $searching) {
            if (property_exists($this, 'filterOwnMusics') && $this->filterOwnMusics) {
                $query->orderBy('updated_at', 'desc');
            } else {
                $query->orderBy('title');
            }
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

    /**
     * Authors keyed by ID for efficient lookups in templates.
     */
    public function getAuthorsByIdProperty()
    {
        return $this->authors->keyBy('id');
    }

    /**
     * Collections keyed by ID for efficient lookups in templates.
     */
    public function getCollectionsByIdProperty()
    {
        return $this->collections->keyBy('id');
    }
}
