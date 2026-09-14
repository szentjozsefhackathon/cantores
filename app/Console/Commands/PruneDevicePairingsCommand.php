<?php

namespace App\Console\Commands;

use App\Models\DevicePairing;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sweeps up after the codes nobody used and the screens nobody came back to.
 */
class PruneDevicePairingsCommand extends Command
{
    protected $signature = 'cantores:prune-device-pairings
                            {--dry-run : Show what would be pruned without making changes}';

    protected $description = 'Delete spent QR pairing codes and retire devices whose session has run out';

    /**
     * How long a revoked pairing is kept, so someone asking "what happened to
     * that laptop" has something to look at.
     */
    private const KEEP_REVOKED_DAYS = 7;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now();

        // Codes that ran out without anyone spending them. An hour's grace past
        // expiry, so a screen still showing one is not pulled out from under it.
        $abandoned = DevicePairing::query()
            ->whereNull('claimed_at')
            ->where('expires_at', '<', $now->copy()->subHour());

        // Devices whose session cannot still exist: the server forgets a session
        // after `session.lifetime` idle minutes, and the list should not claim
        // otherwise.
        $idle = DevicePairing::query()
            ->liveDevices()
            ->where(function ($query) use ($now): void {
                $lifetime = (int) config('session.lifetime');

                $query->where('last_seen_at', '<', $now->copy()->subMinutes($lifetime))
                    ->orWhere(function ($query) use ($now, $lifetime): void {
                        $query->whereNull('last_seen_at')
                            ->where('claimed_at', '<', $now->copy()->subMinutes($lifetime));
                    });
            });

        $stale = DevicePairing::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', $now->copy()->subDays(self::KEEP_REVOKED_DAYS));

        $abandonedCount = $abandoned->count();
        $idleCount = $idle->count();
        $staleCount = $stale->count();

        if ($dryRun) {
            $this->info("Would delete {$abandonedCount} unused code(s).");
            $this->info("Would retire {$idleCount} device(s) whose session has run out.");
            $this->info("Would delete {$staleCount} long-revoked pairing(s).");

            return self::SUCCESS;
        }

        $abandoned->delete();
        $idle->update(['revoked_at' => $now]);
        $stale->delete();

        $this->info("Deleted {$abandonedCount} unused code(s).");
        $this->info("Retired {$idleCount} device(s) whose session has run out.");
        $this->info("Deleted {$staleCount} long-revoked pairing(s).");

        return self::SUCCESS;
    }
}
