<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Music;
use Illuminate\Database\Eloquent\Builder;

class MusicSearchService
{
    /**
     * Search music by text and optional filters.
     *
     * @param  string  $search  The search query
     * @param  array  $filters  Additional filters (e.g., 'genre_id', 'collection_id', 'user_id')
     * @param  array  $options  Search options (e.g., 'use_scout' => true, 'order_by_relevance' => true)
     */
    public function search(string $search, array $filters = [], array $options = []): Builder
    {
        $query = Music::query();

        $this->applySearch($query, $search, $options);
        $this->applyFilters($query, $filters);

        // Default ordering by relevance (if using Scout) or by title
        if ($options['order_by_relevance'] ?? true) {
            $this->orderByRelevance($query, $search, $options);
        }

        return $query;
    }

    /**
     * Apply text search to the query.
     */
    public function applySearch(Builder $query, string $search, array $options = []): void
    {
        $search = trim($search);
        $tokens = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($tokens)) {
            return;
        }

        $query->where(function (Builder $q) use ($tokens, $search, $options) {
            // Scout full‑text search on music fields (title, subtitle, custom_id)
            if ($options['use_scout'] ?? true) {
                $ids = Music::search($search)->keys()->all();
                if (! empty($ids)) {
                    $q->whereIn('id', $ids);
                }
            }

            // Scout search on collection fields (title, abbreviation, author)
            if ($options['use_scout'] ?? true) {
                $collectionIds = Collection::search($search)->keys()->all();
                if (! empty($collectionIds)) {
                    $q->orWhereHas('collections', function (Builder $collectionQuery) use ($collectionIds) {
                        $collectionQuery->whereIn('collections.id', $collectionIds);
                    });
                }
            } else {
                // Fallback to simple ILIKE search on title, abbreviation, author
                $q->orWhereHas('collections', function (Builder $collectionQuery) use ($search) {
                    $collectionQuery->search($search);
                });
            }

            // Numeric pivot fields (order_number) token matching
            $q->orWhereHas('collections', function (Builder $collectionQuery) use ($tokens) {
                $collectionQuery->where(function (Builder $subQuery) use ($tokens) {
                    foreach ($tokens as $token) {
                        $subQuery->orWhere('music_collection.order_number', 'ilike', "%{$token}%");
                    }
                });
            });
        });
    }

    /**
     * Apply additional filters to the query.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['genre_id'])) {
            $query->whereHas('genres', function (Builder $q) use ($filters) {
                $q->where('genres.id', $filters['genre_id']);
            });
        }

        if (isset($filters['collection_id'])) {
            $query->whereHas('collections', function (Builder $q) use ($filters) {
                $q->where('collections.id', $filters['collection_id']);
            });
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Add more filters as needed
    }

    /**
     * Order results by relevance.
     */
    protected function orderByRelevance(Builder $query, string $search, array $options): void
    {
        // If Scout is used, Scout already returns results in relevance order.
        // We can rely on the order of IDs returned by Scout, but we need to preserve that order.
        // Since Scout returns a collection, we can't directly order the query.
        // For simplicity, we'll order by title for now.
        // In a more advanced implementation, we could use a subquery with Scout results.
        $query->orderBy('title');
    }

    /**
     * Get search results as a collection.
     */
    public function get(string $search, array $filters = [], array $options = []): \Illuminate\Database\Eloquent\Collection
    {
        return $this->search($search, $filters, $options)->get();
    }

    /**
     * Get paginated search results.
     */
    public function paginate(string $search, array $filters = [], array $options = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->search($search, $filters, $options)->paginate($perPage);
    }
}
