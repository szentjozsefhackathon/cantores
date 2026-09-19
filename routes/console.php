<?php

use App\Jobs\SyncDiatarCatalogJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('liturgical:warm-cache')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('cantores:prune-device-pairings')
    ->hourly()
    ->withoutOverlapping();

$diatarSchedule = Schedule::job(new SyncDiatarCatalogJob)
    ->monthlyOn((int) config('diatar.schedule_day'), (string) config('diatar.schedule_time'))
    ->withoutOverlapping(180);

if (config('diatar.schedule_on_one_server')) {
    $diatarSchedule->onOneServer();
}
