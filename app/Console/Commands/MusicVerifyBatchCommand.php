<?php

namespace App\Console\Commands;

use App\Models\Music;
use App\Models\MusicVerification;
use App\Models\User;
use Illuminate\Console\Command;

class MusicVerifyBatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cantores:music-verify-batch 
                            {import_batch_number : The import batch number to verify}
                            {--verifier= : ID or email of the user who will be marked as verifier (default: system)}
                            {--force : Skip confirmation prompt}
                            {--dry-run : Show what would be verified without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk verify all music records from a specific import batch';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchNumber = $this->argument('import_batch_number');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Find verifier user
        $verifier = $this->getVerifier();

        if (! $force && ! $dryRun) {
            $musicCount = Music::where('import_batch_number', $batchNumber)->count();

            if ($musicCount === 0) {
                $this->error("No music records found with import_batch_number = {$batchNumber}");

                return self::FAILURE;
            }

            $this->info("Found {$musicCount} music records with import_batch_number = {$batchNumber}");

            if (! $this->confirm('This will create verification records for all fields and relations. Continue?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $musicQuery = Music::where('import_batch_number', $batchNumber)
            ->with([
                'authors',
                'collections',
                'urls',
                'genres',
                'tags',
                'relatedMusic',
                'verifications' => function ($query) use ($verifier) {
                    $query->where('verifier_id', $verifier?->id);
                },
            ]);

        $totalMusic = $musicQuery->count();

        if ($totalMusic === 0) {
            $this->error("No music records found with import_batch_number = {$batchNumber}");

            return self::FAILURE;
        }

        $this->info("Processing {$totalMusic} music records...");

        $bar = $this->output->createProgressBar($totalMusic);
        $bar->start();

        $totalVerifications = 0;
        $skippedVerifications = 0;

        $musicQuery->chunk(100, function ($musicCollection) use ($verifier, $dryRun, &$totalVerifications, &$skippedVerifications, $bar) {
            foreach ($musicCollection as $music) {
                $verificationsCreated = $this->verifyMusic($music, $verifier, $dryRun);
                $totalVerifications += $verificationsCreated['created'];
                $skippedVerifications += $verificationsCreated['skipped'];
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("[DRY RUN] Would create {$totalVerifications} verification records");
            $this->info("[DRY RUN] Would skip {$skippedVerifications} already verified records");
        } else {
            $this->info("Successfully created {$totalVerifications} verification records");
            $this->info("Skipped {$skippedVerifications} already verified records");
        }

        return self::SUCCESS;
    }

    /**
     * Get the verifier user.
     */
    private function getVerifier(): ?User
    {
        $verifierInput = $this->option('verifier');

        if (empty($verifierInput)) {
            $this->warn('No verifier specified - verifications will be marked as system verified');

            return null;
        }

        // Try to find by ID
        if (is_numeric($verifierInput)) {
            $verifier = User::find($verifierInput);
        } else {
            // Try to find by email
            $verifier = User::where('email', $verifierInput)->first();
        }

        if (! $verifier) {
            $this->error("Verifier not found: {$verifierInput}");
            $this->warn('Continuing without verifier (system verified)');

            return null;
        }

        $this->info("Using verifier: {$verifier->name} ({$verifier->email})");

        return $verifier;
    }

    /**
     * Create verification records for all fields and relations of a music record.
     *
     * @return array{created: int, skipped: int}
     */
    private function verifyMusic(Music $music, ?User $verifier, bool $dryRun): array
    {
        $created = 0;
        $skipped = 0;

        // Direct fields
        $directFields = ['title', 'subtitle', 'custom_id'];
        foreach ($directFields as $field) {
            if ($this->createVerification($music, $field, null, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // Authors
        foreach ($music->authors as $author) {
            if ($this->createVerification($music, 'author', $author->id, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // Collections
        foreach ($music->collections as $collection) {
            if ($this->createVerification($music, 'collection', $collection->id, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // URLs
        foreach ($music->urls as $url) {
            if ($this->createVerification($music, 'url', $url->id, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // Genres
        foreach ($music->genres as $genre) {
            if ($this->createVerification($music, 'genre', $genre->id, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // Tags
        foreach ($music->tags as $tag) {
            if ($this->createVerification($music, 'tag', $tag->id, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // Related music
        foreach ($music->relatedMusic as $related) {
            if ($this->createVerification($music, 'related_music', $related->id, $verifier, $dryRun)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Create a single verification record if it doesn't already exist.
     */
    private function createVerification(Music $music, string $fieldName, ?int $pivotReference, ?User $verifier, bool $dryRun): bool
    {
        // Check if verification already exists
        $query = MusicVerification::where('music_id', $music->id)
            ->where('field_name', $fieldName)
            ->where('pivot_reference', $pivotReference);

        if ($verifier) {
            $query->where('verifier_id', $verifier->id);
        }

        if ($query->exists()) {
            return false;
        }

        if (! $dryRun) {
            MusicVerification::create([
                'music_id' => $music->id,
                'verifier_id' => $verifier?->id,
                'field_name' => $fieldName,
                'pivot_reference' => $pivotReference,
                'status' => 'verified',
                'verified_at' => now(),
                'notes' => 'Bulk verified via artisan command',
            ]);
        }

        return true;
    }
}
