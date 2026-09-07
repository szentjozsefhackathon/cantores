<?php

use App\Jobs\RenderScoreFileJob;
use App\Models\ScoreFile;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

it('queues the rendered files that have no systems yet', function () {
    Queue::fake();

    $uncut = ScoreFile::factory()->ready()->create();
    $cut = ScoreFile::factory()->banded()->create();

    artisan('scores:cut-systems')->assertSuccessful();

    Queue::assertPushed(RenderScoreFileJob::class, 1);
    Queue::assertPushed(
        RenderScoreFileJob::class,
        fn (RenderScoreFileJob $job): bool => $job->scoreFile->is($uncut)
    );
});

it('leaves a file that never rendered alone', function () {
    Queue::fake();

    ScoreFile::factory()->failed()->create();

    artisan('scores:cut-systems')->assertSuccessful();

    Queue::assertNothingPushed();
});

// Bytes kept alive only because a published version points at them are not part
// of any score any more, so nothing will ever ask them for a system.
it('leaves a superseded file alone', function () {
    Queue::fake();

    ScoreFile::factory()->ready()->create(['superseded_at' => now()]);

    artisan('scores:cut-systems')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('re-cuts everything when asked to', function () {
    Queue::fake();

    ScoreFile::factory()->ready()->create();
    ScoreFile::factory()->banded()->create();

    artisan('scores:cut-systems', ['--all' => true])->assertSuccessful();

    Queue::assertPushed(RenderScoreFileJob::class, 2);
});

it('stops at the limit it was given', function () {
    Queue::fake();

    ScoreFile::factory()->ready()->count(3)->create();

    artisan('scores:cut-systems', ['--limit' => 2])->assertSuccessful();

    Queue::assertPushed(RenderScoreFileJob::class, 2);
});
