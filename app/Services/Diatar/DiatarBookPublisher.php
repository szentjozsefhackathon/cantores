<?php

namespace App\Services\Diatar;

use App\Models\DiatarBook;
use App\Models\DiatarSlide;
use App\Models\DiatarSyncRun;
use Illuminate\Support\Facades\DB;

class DiatarBookPublisher
{
    /**
     * @param  array{
     *     book: array{title: string, short_name: ?string, group: ?string, source_order: int, available: bool, diagnostic_reason: ?string},
     *     songs: list<array{title: string, reference: ?string, source_order: int, available: bool, diagnostic_reason: ?string, slides: list<array{source_order: int, external_id: ?string, verse_name: string, is_exportable: bool, diagnostic_reason: ?string}>}>,
     *     warnings: list<string>
     * }  $parsed
     */
    public function publish(
        DiatarSyncRun $run,
        string $sourcePath,
        string $checksum,
        int $treeOrder,
        array $parsed,
    ): DiatarBook {
        return DB::transaction(function () use ($run, $sourcePath, $checksum, $treeOrder, $parsed): DiatarBook {
            $book = DiatarBook::query()
                ->where('source_path', $sourcePath)
                ->lockForUpdate()
                ->first();
            $attributes = [
                'source_revision' => $run->source_revision,
                'checksum' => $checksum,
                'available' => $parsed['book']['available'],
                'unavailable_reason' => $parsed['book']['diagnostic_reason'],
                'last_seen_sync_run_id' => $run->id,
                'source_order' => $parsed['book']['source_order'] ?: $treeOrder,
            ];

            if ($book === null || $parsed['book']['available']) {
                $attributes = [
                    ...$attributes,
                    'title' => $parsed['book']['title'],
                    'short_name' => $parsed['book']['short_name'],
                    'group' => $parsed['book']['group'],
                ];
            }

            if ($book === null) {
                $book = DiatarBook::query()->create([
                    'source_path' => $sourcePath,
                    ...$attributes,
                ]);
            } else {
                $book->update($attributes);
            }

            if (! $parsed['book']['available']) {
                $book->songs()->update([
                    'available' => false,
                    'diagnostic_reason' => 'source_unavailable',
                    'last_seen_sync_run_id' => $run->id,
                ]);
                DiatarSlide::query()
                    ->whereIn('diatar_song_id', $book->songs()->select('id'))
                    ->update([
                        'is_exportable' => false,
                        'diagnostic_reason' => 'source_unavailable',
                        'last_seen_sync_run_id' => $run->id,
                    ]);

                return $book;
            }

            $songOrdinals = [];
            foreach ($parsed['songs'] as $songData) {
                $songOrdinals[] = $songData['source_order'];
                $song = $book->songs()->updateOrCreate(
                    ['source_order' => $songData['source_order']],
                    [
                        'title' => $songData['title'],
                        'reference' => $songData['reference'],
                        'available' => $songData['available'],
                        'diagnostic_reason' => $songData['diagnostic_reason'],
                        'last_seen_sync_run_id' => $run->id,
                    ],
                );

                $slideOrdinals = [];
                foreach ($songData['slides'] as $slideData) {
                    $slideOrdinals[] = $slideData['source_order'];
                    $song->slides()->updateOrCreate(
                        ['source_order' => $slideData['source_order']],
                        [
                            'external_id' => $slideData['external_id'],
                            'verse_name' => $slideData['verse_name'],
                            'is_exportable' => $slideData['is_exportable'],
                            'diagnostic_reason' => $slideData['diagnostic_reason'],
                            'last_seen_sync_run_id' => $run->id,
                        ],
                    );
                }

                $song->slides()->whereNotIn('source_order', $slideOrdinals ?: [-1])->delete();
            }

            $book->songs()->whereNotIn('source_order', $songOrdinals ?: [-1])->delete();

            return $book;
        }, attempts: 3);
    }

    public function quarantineDuplicateSlideIds(): int
    {
        return DB::transaction(function (): int {
            DiatarSlide::query()
                ->where('diagnostic_reason', 'duplicate_external_id')
                ->whereHas('song', fn ($query) => $query
                    ->where('available', true)
                    ->whereHas('book', fn ($bookQuery) => $bookQuery->where('available', true)))
                ->update([
                    'is_exportable' => true,
                    'diagnostic_reason' => null,
                ]);

            $duplicateIds = DiatarSlide::query()
                ->select('external_id')
                ->whereNotNull('external_id')
                ->whereHas('song', fn ($query) => $query
                    ->where('available', true)
                    ->whereHas('book', fn ($bookQuery) => $bookQuery->where('available', true)))
                ->groupBy('external_id')
                ->havingRaw('count(*) > 1')
                ->pluck('external_id');

            if ($duplicateIds->isEmpty()) {
                return 0;
            }

            return DiatarSlide::query()
                ->whereIn('external_id', $duplicateIds)
                ->update([
                    'is_exportable' => false,
                    'diagnostic_reason' => 'duplicate_external_id',
                ]);
        });
    }
}
