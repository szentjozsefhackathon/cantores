<?php

namespace App\Services\Diatar;

use App\Enums\DiatarSyncStatus;
use App\Models\DiatarBook;
use App\Models\DiatarSyncRun;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DiatarCatalogSynchronizer
{
    public function __construct(
        private DiatarRepositoryClient $repository,
        private DiatarDtxMetadataParser $parser,
        private DiatarBookPublisher $publisher,
    ) {}

    /**
     * @param  null|Closure(int, int, ?string): void  $onProgress
     */
    public function synchronize(?Closure $onProgress = null): DiatarSyncRun
    {
        $run = DiatarSyncRun::query()->create([
            'status' => DiatarSyncStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $tree = $this->repository->tree();
        } catch (Throwable $exception) {
            $run->update([
                'status' => DiatarSyncStatus::Failed,
                'completed_at' => now(),
                'error_summary' => Str::limit($exception->getMessage(), 4000, ''),
            ]);

            throw $exception;
        }

        try {
            $run->update([
                'source_revision' => $tree['revision'],
                'fetched_count' => count($tree['files']),
            ]);

            $indexed = 0;
            $skipped = 0;
            $unavailable = 0;
            $warnings = [];
            $fileCount = count($tree['files']);
            $sourcePaths = collect($tree['files'])->pluck('path')->all();
            $onProgress?->__invoke(0, $fileCount, null);

            foreach ($tree['files'] as $index => $file) {
                try {
                    try {
                        $contents = $this->repository->contents($file['path'], $tree['revision']);
                    } catch (Throwable $exception) {
                        $skipped++;
                        $warnings[] = "{$file['path']}: download failed ({$exception->getMessage()}).";

                        continue;
                    }

                    try {
                        $parsed = $this->parser->parse($file['path'], $contents);
                    } catch (Throwable $exception) {
                        report($exception);
                        $parsed = $this->unavailableParseResult($file['path']);
                        $warnings[] = "{$file['path']}: parsing failed ({$exception->getMessage()}).";
                    }
                    foreach ($parsed['warnings'] as $warning) {
                        $warnings[] = "{$file['path']}: {$warning}";
                    }

                    try {
                        $book = $this->publisher->publish(
                            $run,
                            $file['path'],
                            $file['checksum'] !== '' ? $file['checksum'] : hash('sha256', $contents),
                            $file['order'],
                            $parsed,
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                        $skipped++;
                        $warnings[] = "{$file['path']}: publication failed ({$exception->getMessage()}).";

                        continue;
                    }

                    if ($book->available) {
                        $indexed++;
                    } else {
                        $unavailable++;
                    }
                } finally {
                    $onProgress?->__invoke($index + 1, $fileCount, $file['path']);
                }
            }

            DB::transaction(function () use ($run, $sourcePaths, &$unavailable): void {
                $missingBooks = DiatarBook::query()
                    ->whereNotIn('source_path', $sourcePaths ?: [''])
                    ->where('available', true)
                    ->get();

                foreach ($missingBooks as $book) {
                    $book->update([
                        'available' => false,
                        'unavailable_reason' => 'missing_from_authoritative_tree',
                        'last_seen_sync_run_id' => $run->id,
                    ]);
                    $book->songs()->update([
                        'available' => false,
                        'diagnostic_reason' => 'source_unavailable',
                        'last_seen_sync_run_id' => $run->id,
                    ]);
                    $book->songs()->each(fn ($song) => $song->slides()->update([
                        'is_exportable' => false,
                        'diagnostic_reason' => 'source_unavailable',
                        'last_seen_sync_run_id' => $run->id,
                    ]));
                    $unavailable++;
                }
            });

            $duplicateCount = $this->publisher->quarantineDuplicateSlideIds();
            if ($duplicateCount > 0) {
                $warnings[] = "{$duplicateCount} slides were quarantined because their IDs are ambiguous.";
            }

            $uniqueWarnings = array_values(array_unique($warnings));
            $warningSummary = collect($uniqueWarnings)->take(50)->implode("\n");
            $warningCount = count($uniqueWarnings);

            $run->update([
                'status' => $warningCount > 0
                    ? DiatarSyncStatus::CompletedWithWarnings
                    : DiatarSyncStatus::Completed,
                'indexed_count' => $indexed,
                'skipped_count' => $skipped,
                'unavailable_count' => $unavailable,
                'warning_count' => $warningCount,
                'completed_at' => now(),
                'error_summary' => $warningSummary !== '' ? Str::limit($warningSummary, 4000, '') : null,
            ]);

            Log::info('Diatár catalogue synchronization completed.', [
                'run_id' => $run->id,
                'revision' => $run->source_revision,
                'indexed' => $indexed,
                'skipped' => $skipped,
                'unavailable' => $unavailable,
                'warnings' => $warningCount,
            ]);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => DiatarSyncStatus::Failed,
                'completed_at' => now(),
                'error_summary' => Str::limit($exception->getMessage(), 4000, ''),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     book: array{title: string, short_name: null, group: null, source_order: int, available: false, diagnostic_reason: string},
     *     songs: array{},
     *     warnings: array{}
     * }
     */
    private function unavailableParseResult(string $sourcePath): array
    {
        return [
            'book' => [
                'title' => pathinfo($sourcePath, PATHINFO_FILENAME),
                'short_name' => null,
                'group' => null,
                'source_order' => 0,
                'available' => false,
                'diagnostic_reason' => 'parse_failed',
            ],
            'songs' => [],
            'warnings' => [],
        ];
    }
}
