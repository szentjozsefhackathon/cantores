<?php

namespace App\Console\Commands;

use App\Models\Music;
use App\Models\User;
use Illuminate\Console\Command;

class MusicDeleteRecentCommand extends Command
{
    protected $signature = 'cantores:music-delete-recent
                            {minutes : Number of minutes to look back (1, 5, or 60)}
                            {user : ID or email of the user whose music to delete}
                            {--force : Skip confirmation prompt}
                            {--dry-run : Show what would be deleted without making changes}';

    protected $description = 'Delete music records created in the last 1, 5, or 60 minutes';

    public function handle(): int
    {
        $minutes = (int) $this->argument('minutes');

        if (! in_array($minutes, [1, 5, 60])) {
            $this->error('Invalid minutes value. Allowed values are: 1, 5, 60.');

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $user = $this->resolveUser();

        if ($user === false) {
            return self::FAILURE;
        }

        $query = Music::query()
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->where('user_id', $user->id);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No music records found in the specified time range.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} music record(s) created in the last {$minutes} minute(s) by {$user->name} ({$user->email}).");

        if ($dryRun) {
            $this->info("[DRY RUN] Would delete {$count} music record(s).");

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Are you sure you want to delete these {$count} record(s)? This cannot be undone.", false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $query->each(fn (Music $music) => $music->delete());

        $this->info("Successfully deleted {$count} music record(s).");

        return self::SUCCESS;
    }

    /**
     * Resolve the user from the user argument.
     */
    private function resolveUser(): User|false
    {
        $userInput = $this->argument('user');

        $user = is_numeric($userInput)
            ? User::find($userInput)
            : User::where('email', $userInput)->first();

        if (! $user) {
            $this->error("User not found: {$userInput}");

            return false;
        }

        return $user;
    }
}
