<?php

namespace App\Console\Commands;

use App\Services\LiturgicalInfoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WarmLiturgicalCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liturgical:warm-cache {--days=2 : Number of days (starting today) to warm}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-fetch and cache liturgical information for the upcoming days so the dashboard never blocks on a cold cache';

    /**
     * Execute the console command.
     */
    public function handle(LiturgicalInfoService $service): int
    {
        $days = max(1, (int) $this->option('days'));
        $failures = 0;

        for ($offset = 0; $offset < $days; $offset++) {
            $date = Carbon::today()->addDays($offset)->format('Y-m-d');

            if ($service->getForDate($date, forceRefresh: true) !== null) {
                $this->components->info("Warmed liturgical cache for {$date}.");
            } else {
                $failures++;
                $this->components->warn("Failed to warm liturgical cache for {$date}.");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
