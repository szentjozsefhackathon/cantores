<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\Score;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sitemap, enumerated from the database rather than crawled.
 *
 * Cached for an hour and flushed by ScorePublicationService whenever a score
 * enters or leaves the library, so an unpublished score stops being advertised
 * without waiting for the cache to age out.
 */
class SitemapController extends Controller
{
    public const CACHE_KEY = 'sitemap';

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function build(): string
    {
        return view('sitemap', ['urls' => $this->urls()])->render();
    }

    /**
     * @return list<array{loc: string, lastmod: ?string, priority: string}>
     */
    private function urls(): array
    {
        $urls = [];

        foreach (['home', 'about', 'guide', 'music-database', 'collections', 'authors', 'music-plans', 'public-scores'] as $name) {
            $urls[] = ['loc' => route($name), 'lastmod' => null, 'priority' => '0.8'];
        }

        Score::query()
            ->published()
            ->select(['id', 'title', 'updated_at'])
            ->chunkById(500, function ($scores) use (&$urls): void {
                foreach ($scores as $score) {
                    $urls[] = [
                        'loc' => route('public-scores.show', [
                            'score' => $score,
                            'slug' => Str::slug($score->title) ?: 'kotta',
                        ]),
                        'lastmod' => $score->updated_at?->toAtomString(),
                        'priority' => '0.9',
                    ];
                }
            });

        Music::query()
            ->public()
            ->select(['id', 'updated_at'])
            ->chunkById(1000, function ($musics) use (&$urls): void {
                foreach ($musics as $music) {
                    $urls[] = [
                        'loc' => route('music-view', $music),
                        'lastmod' => $music->updated_at?->toAtomString(),
                        'priority' => '0.6',
                    ];
                }
            });

        Collection::query()
            ->public()
            ->select(['id', 'updated_at'])
            ->chunkById(500, function ($collections) use (&$urls): void {
                foreach ($collections as $collection) {
                    $urls[] = [
                        'loc' => route('collection-view', $collection),
                        'lastmod' => $collection->updated_at?->toAtomString(),
                        'priority' => '0.5',
                    ];
                }
            });

        Author::query()
            ->public()
            ->select(['id', 'updated_at'])
            ->chunkById(500, function ($authors) use (&$urls): void {
                foreach ($authors as $author) {
                    $urls[] = [
                        'loc' => route('author-view', $author),
                        'lastmod' => $author->updated_at?->toAtomString(),
                        'priority' => '0.5',
                    ];
                }
            });

        MusicPlan::query()
            ->public()
            ->select(['id', 'updated_at'])
            ->chunkById(500, function ($plans) use (&$urls): void {
                foreach ($plans as $plan) {
                    $urls[] = [
                        'loc' => route('music-plan-view', $plan),
                        'lastmod' => $plan->updated_at?->toAtomString(),
                        'priority' => '0.4',
                    ];
                }
            });

        return $urls;
    }
}
