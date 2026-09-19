<?php

namespace App\Services\Diatar;

use App\Models\DiatarMusicBinding;
use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use App\Models\Music;
use Illuminate\Support\Collection;

class DiatarCandidateResolver
{
    /**
     * @return array{status: string, selected_song_id: ?int, candidates: list<array<string, mixed>>}
     */
    public function resolve(Music $music): array
    {
        $activeBindings = $music->diatarBindings
            ->where('is_active', true)
            ->sortBy('id')
            ->values();

        if ($activeBindings->isNotEmpty()) {
            $candidates = $activeBindings
                ->map(fn (DiatarMusicBinding $binding): ?array => $this->bindingCandidate($binding))
                ->filter()
                ->values();

            return [
                'status' => $candidates->count() === 1 ? 'resolved' : ($candidates->isEmpty() ? 'unresolved' : 'ambiguous'),
                'selected_song_id' => $candidates->count() === 1 ? $candidates->first()['song_id'] : null,
                'candidates' => $candidates->all(),
            ];
        }

        $candidates = collect();
        $ambiguous = false;
        $collections = $music->collections
            ->sortBy([
                ['priority', 'asc'],
                ['is_verified', 'desc'],
                ['id', 'asc'],
            ])
            ->values();

        foreach ($collections as $collectionIndex => $collection) {
            $reference = trim((string) $collection->pivot->order_number);
            if ($reference === '') {
                continue;
            }

            $books = $collection->diatarBooks
                ->where('available', true)
                ->sortBy([
                    [fn ($book): int => $book->pivot->is_default ? 0 : 1, 'asc'],
                    ['source_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            foreach ($books as $bookIndex => $book) {
                $matches = $book->songs
                    ->where('available', true)
                    ->filter(fn (DiatarSong $song): bool => $this->referencesMatch($reference, $song->reference))
                    ->values();

                if ($matches->count() > 1) {
                    $ambiguous = true;
                }

                foreach ($matches as $song) {
                    $slides = $song->slides->where('is_exportable', true)->whereNotNull('external_id')->values();
                    if ($slides->isEmpty()) {
                        continue;
                    }

                    $rank = ($book->pivot->is_default ? 100 : 200) + ($collectionIndex * 1000) + $bookIndex;
                    $candidate = $this->candidate($song, $slides, $rank, false);
                    $existing = $candidates->get($song->id);

                    if ($existing === null || $candidate['rank'] < $existing['rank']) {
                        $candidates->put($song->id, $candidate);
                    }
                }
            }
        }

        $candidates = $candidates
            ->sortBy([
                ['rank', 'asc'],
                ['song_order', 'asc'],
                ['song_id', 'asc'],
            ])
            ->values();

        if ($candidates->isEmpty()) {
            return [
                'status' => $ambiguous ? 'ambiguous' : 'unresolved',
                'selected_song_id' => null,
                'candidates' => [],
            ];
        }

        return [
            'status' => $ambiguous ? 'ambiguous' : 'resolved',
            'selected_song_id' => $ambiguous ? null : $candidates->first()['song_id'],
            'candidates' => $candidates->all(),
        ];
    }

    private function bindingCandidate(DiatarMusicBinding $binding): ?array
    {
        $song = $binding->song;
        if (! $song->available || ! $song->book->available) {
            return null;
        }

        $slides = $binding->slides->isNotEmpty()
            ? $binding->slides->pluck('slide')
            : $song->slides;
        $slides = $slides->filter(
            fn (?DiatarSlide $slide): bool => $slide !== null
                && $slide->diatar_song_id === $song->id
                && $slide->is_exportable
                && $slide->external_id !== null,
        )->values();

        if ($slides->isEmpty()) {
            return null;
        }

        return $this->candidate($song, $slides, 0, true);
    }

    /**
     * @param  Collection<int, DiatarSlide>  $slides
     * @return array<string, mixed>
     */
    private function candidate(DiatarSong $song, Collection $slides, int $rank, bool $explicit): array
    {
        return [
            'song_id' => $song->id,
            'book_id' => $song->diatar_book_id,
            'label' => $song->title.' — '.$song->book->source_path,
            'book_title' => $song->book->title,
            'book_path' => $song->book->source_path,
            'song_title' => $song->title,
            'song_order' => $song->source_order,
            'rank' => $rank,
            'explicit' => $explicit,
            'slides' => $slides->map(fn (DiatarSlide $slide): array => [
                'id' => $slide->id,
                'external_id' => $slide->external_id,
                'verse_name' => $slide->verse_name,
            ])->all(),
        ];
    }

    private function referencesMatch(string $collectionReference, ?string $songReference): bool
    {
        if ($songReference === null) {
            return false;
        }

        return mb_strtoupper(trim($collectionReference)) === mb_strtoupper(trim($songReference));
    }
}
