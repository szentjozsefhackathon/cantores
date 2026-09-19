<?php

namespace App\Console\Commands;

use App\Jobs\SyncDiatarCatalogJob;
use App\Services\Diatar\DiatarCatalogSynchronizer;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class SyncDiatarCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:sync-diatar-catalog {--queue : Dispatch the synchronization to the queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the metadata-only Diatár catalogue';

    /**
     * Execute the console command.
     */
    public function handle(DiatarCatalogSynchronizer $synchronizer): int
    {
        if ($this->option('queue')) {
            SyncDiatarCatalogJob::dispatch();
            $this->info('Diatár catalogue synchronization queued.');

            return self::SUCCESS;
        }

        $this->info('Fetching the Diatár repository catalogue...');

        $progressBar = null;
        $run = $synchronizer->synchronize(function (int $processed, int $total, ?string $sourcePath) use (&$progressBar): void {
            if ($progressBar === null) {
                $this->info($total === 1
                    ? 'Processing 1 catalogue file...'
                    : "Processing {$total} catalogue files...");

                if ($total === 0) {
                    return;
                }

                $progressBar = $this->output->createProgressBar($total);
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                $progressBar->setMessage('Starting...');
                $progressBar->start();
            }

            if ($progressBar instanceof ProgressBar && $sourcePath !== null) {
                $progressBar->setMessage($sourcePath);
                $progressBar->setProgress($processed);
            }
        });

        if ($progressBar instanceof ProgressBar) {
            $progressBar->finish();
            $this->newLine(2);
        }

        $this->table(
            ['Run', 'Revision', 'Status', 'Fetched', 'Indexed', 'Skipped', 'Unavailable', 'Warnings'],
            [[
                $run->id,
                $run->source_revision,
                $run->status->value,
                $run->fetched_count,
                $run->indexed_count,
                $run->skipped_count,
                $run->unavailable_count,
                $run->warning_count,
            ]],
        );

        return self::SUCCESS;
    }
}
