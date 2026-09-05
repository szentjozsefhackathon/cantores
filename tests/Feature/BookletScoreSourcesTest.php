<?php

use App\Models\Loan;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\LoanKeepingService;
use App\Services\MusicPlanScoreListService;

/**
 * What a booklet is allowed to draw.
 *
 * sourcesFor() is the only place the score's actual content leaves the server for
 * the booklet, so it answers the same three-axis question the service list does —
 * the library, my own, and what I kept out of a live loan — and it is resolved on
 * every render rather than captured when a score is added.
 */
function sources(array $scoreIds, ?User $viewer)
{
    return app(MusicPlanScoreListService::class)->sourcesFor($scoreIds, $viewer);
}

it('gives me my own score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    expect(sources([$score->id], $user)->has($score->id))->toBeTrue();
});

it('withholds a score belonging to someone else', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);

    expect(sources([$score->id], $stranger)->has($score->id))->toBeFalse();
});

it('gives anyone a published score, and its credit line with it', function () {
    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    ScorePublication::factory()->approved()->create(['score_id' => $score->id]);

    $source = sources([$score->id], $reader)->get($score->id);

    expect($source)->not->toBeNull()
        ->and($source['credit'])->not->toBeNull();
});

it('gives me a score I kept out of a live loan', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    app(LoanKeepingService::class)->keep($loan, $borrower);

    expect(sources([$score->id], $borrower)->has($score->id))->toBeTrue();
});

// The reason the booklet stores references and never a copy: recalling a loan has
// to actually take the music back, including out of a booklet already built.
it('drops a borrowed score the moment the loan is recalled', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    app(LoanKeepingService::class)->keep($loan, $borrower);
    expect(sources([$score->id], $borrower)->has($score->id))->toBeTrue();

    $loan->revoke();

    expect(sources([$score->id], $borrower)->has($score->id))->toBeFalse();
});

it('withholds everything from a guest except the library', function () {
    $owner = User::factory()->create();
    $private = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $published = Score::factory()->abc()->create(['user_id' => $owner->id]);
    ScorePublication::factory()->approved()->create(['score_id' => $published->id]);

    $found = sources([$private->id, $published->id], null);

    expect($found->has($published->id))->toBeTrue()
        ->and($found->has($private->id))->toBeFalse();
});

it('skips a links-only score, which has nothing to engrave', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'format' => null, 'content' => null]);

    expect(sources([$score->id], $user)->has($score->id))->toBeFalse();
});

it('asks nothing of the database for an empty booklet', function () {
    expect(sources([], User::factory()->create())->isEmpty())->toBeTrue();
});
