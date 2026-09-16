<?php

namespace App\Http\Controllers;

use App\Concerns\HasMusicSearchScopes;
use App\Http\Requests\ProjectionMusicSearchRequest;
use App\Models\Music;
use App\Models\Projection;
use Illuminate\Http\JsonResponse;

/**
 * The remote's music search, for a song the plan does not have.
 *
 * JSON rather than the Livewire music search, for the reason the whole remote is:
 * nothing may touch the wire:ignore'd canvas in the middle of a service. The
 * query is the library's own (HasMusicSearchScopes), cut down to what a thumb
 * types: a title, or — when a number is typed — a collection and its number.
 */
class ProjectionMusicSearchController extends Controller
{
    use HasMusicSearchScopes;

    private const LIMIT = 15;

    public string $search = '';

    public string $collectionFilter = '';

    public string $collectionFreeText = '';

    public string $authorFilter = '';

    public string $authorFreeText = '';

    public function __invoke(ProjectionMusicSearchRequest $request, Projection $projection): JsonResponse
    {
        $term = $request->term();

        if ($term === '') {
            return response()->json(['musics' => []]);
        }

        if (preg_match('/\d/', $term) === 1) {
            $this->collectionFreeText = $term;
            $musics = $this->applyScopes(Music::query(), searching: false)->limit(self::LIMIT)->get();
        } else {
            $this->search = $term;
            $musics = Music::search($term)
                ->query(fn ($query) => $this->applyScopes($query, searching: true))
                ->take(self::LIMIT)
                ->get();
        }

        return response()->json([
            'musics' => $musics
                ->map(fn (Music $music): array => [
                    'id' => $music->id,
                    'title' => $music->title,
                    'subtitle' => $music->subtitle,
                    'reference' => $music->collectionReference($request->user()),
                ])
                ->values()
                ->all(),
        ]);
    }
}
