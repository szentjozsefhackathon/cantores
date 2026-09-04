<?php

use App\Livewire\Pages\MusicPlanLoanView;
use App\Livewire\Pages\ScoreView;
use App\Models\Loan;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Márta lends a score to Béla; Béla puts it in his own plan and lends that to
 * Ilonka. Reading resolves through the loan actually opened, keeping records the
 * root loan — an intermediary is a route, not a rights-holder.
 */
function lendingChain(): array
{
    $marta = User::factory()->create(['name' => 'Márta']);
    $bela = User::factory()->create(['name' => 'Béla']);
    $ilonka = User::factory()->create(['name' => 'Ilonka']);

    $music = Music::factory()->create(['user_id' => $marta->id]);
    $martaScore = Score::factory()->create([
        'user_id' => $marta->id,
        'music_id' => $music->id,
        'title' => 'Márta feldolgozása',
    ]);

    $martaLoan = Loan::factory()->of($martaScore)->create();
    app(LoanKeepingService::class)->keep($martaLoan, $bela);

    $belaPlan = MusicPlan::factory()->create(['user_id' => $bela->id]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $belaPlan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);
    $belaLoan = Loan::factory()->of($belaPlan)->create();

    return compact('marta', 'bela', 'ilonka', 'music', 'martaScore', 'martaLoan', 'belaPlan', 'belaLoan');
}

it('carries a borrowed score onward through the intermediary plan loan', function () {
    ['martaScore' => $score, 'belaLoan' => $belaLoan] = lendingChain();

    expect(app(LoanAccessService::class)->grantsScore($belaLoan, $score))->toBeTrue();

    Livewire::test(MusicPlanLoanView::class, ['token' => $belaLoan->token])
        ->assertSee('Márta feldolgozása');
});

it('closes the whole chain when the owner recalls their loan', function () {
    ['martaScore' => $score, 'martaLoan' => $martaLoan, 'belaLoan' => $belaLoan] = lendingChain();

    $martaLoan->revoke();

    expect(app(LoanAccessService::class)->grantsScore($belaLoan->fresh(), $score))->toBeFalse();

    Livewire::test(ScoreView::class, ['token' => $belaLoan->token, 'score' => $score])
        ->assertNotFound();
});

it('records the root loan rather than the intermediary when a chain reader keeps a score', function () {
    ['ilonka' => $ilonka, 'martaScore' => $score, 'martaLoan' => $martaLoan, 'belaLoan' => $belaLoan] = lendingChain();

    actingAs($ilonka);

    Livewire::test(ScoreView::class, ['token' => $belaLoan->token, 'score' => $score])
        ->call('keep');

    $receipt = ReceivedLoan::query()->kept()->where('user_id', $ilonka->id)->first();

    // Márta's loan is of that one score, so there is no narrower scope to record —
    // and access from here on depends on Márta alone, not on Béla.
    expect($receipt)->not->toBeNull()
        ->and($receipt->loan_id)->toBe($martaLoan->id)
        ->and($receipt->loan_id)->not->toBe($belaLoan->id)
        ->and(app(LoanAccessService::class)->keptScoreIds($ilonka))->toBe([$score->id]);
});

it('leaves a kept score alone when the intermediary deletes their plan loan', function () {
    ['ilonka' => $ilonka, 'martaScore' => $score, 'martaLoan' => $martaLoan, 'belaLoan' => $belaLoan] = lendingChain();

    actingAs($ilonka);
    Livewire::test(ScoreView::class, ['token' => $belaLoan->token, 'score' => $score])->call('keep');

    $belaLoan->revoke();

    // Béla was a route, not a rights-holder: Márta's loan still opens her score.
    expect(app(LoanAccessService::class)->keptScoreIds($ilonka->fresh()))->toContain($score->id);

    Livewire::test(ScoreView::class, ['token' => $martaLoan->token])->assertOk();
});

it('does not widen a folder loan into a kept folder when one score is kept', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $folder = \App\Models\Folder::factory()->create(['user_id' => $owner->id]);
    $kept = Score::factory()->create(['user_id' => $owner->id]);
    $other = Score::factory()->create(['user_id' => $owner->id]);
    $folder->scores()->attach([$kept->id, $other->id]);

    $loan = Loan::factory()->of($folder)->create();

    actingAs($borrower);
    Livewire::test(ScoreView::class, ['token' => $loan->token, 'score' => $kept])->call('keep');

    $receipt = ReceivedLoan::query()->kept()->where('user_id', $borrower->id)->first();

    expect($receipt->score_id)->toBe($kept->id)
        ->and(app(LoanAccessService::class)->keptScoreIds($borrower))
        ->toBe([$kept->id]);
});

it('stops a borrowed score at a published plan', function () {
    ['bela' => $bela, 'belaPlan' => $plan, 'martaScore' => $borrowed, 'music' => $music] = lendingChain();

    $own = Score::factory()->create([
        'user_id' => $bela->id,
        'music_id' => $music->id,
        'title' => 'Béla saját kottája',
    ]);

    $plan->update(['is_private' => false]);

    // A stranger reading the published plan holds neither score.
    $stranger = User::factory()->create();
    actingAs($stranger);

    $visible = app(\App\Services\MusicPlanScoreListService::class)
        ->forViewer($plan->fresh(), $stranger)
        ->flatten(1)
        ->pluck('id');

    expect($visible)->not->toContain($borrowed->id)
        ->and($visible)->not->toContain($own->id);
});

it('passes a borrowed score on through my folder only while I still hold it', function () {
    $lender = User::factory()->create();
    $borrower = User::factory()->create();

    $borrowed = Score::factory()->create(['user_id' => $lender->id, 'title' => 'Kölcsönbe kapott']);
    $own = Score::factory()->create(['user_id' => $borrower->id, 'title' => 'Saját kotta']);

    $lenderLoan = Loan::factory()->of($borrowed)->create();
    $receipt = app(LoanKeepingService::class)->keep($lenderLoan, $borrower);

    $folder = \App\Models\Folder::factory()->create(['user_id' => $borrower->id]);
    $folder->scores()->attach([$own->id, $borrowed->id]);

    $folderLoan = Loan::factory()->of($folder)->create();

    expect(app(LoanAccessService::class)->scoreIdsFor($folderLoan))
        ->toEqualCanonicalizing([$own->id, $borrowed->id]);

    // The owner changing the lock closes it for everyone downstream at once,
    // without anything having to be unpicked from the folder.
    $lenderLoan->revoke();

    expect(app(LoanAccessService::class)->scoreIdsFor($folderLoan->fresh()))->toBe([$own->id])
        ->and($receipt->fresh())->not->toBeNull();
});

it('files a borrowed score into one of my own folders', function () {
    $lender = User::factory()->create();
    $borrower = User::factory()->create();

    $borrowed = Score::factory()->create(['user_id' => $lender->id]);
    $loan = Loan::factory()->of($borrowed)->create();
    $receipt = app(LoanKeepingService::class)->keep($loan, $borrower);

    $folder = \App\Models\Folder::factory()->create(['user_id' => $borrower->id, 'name' => 'Advent']);

    actingAs($borrower);

    Livewire::test(\App\Livewire\Pages\Loans::class)
        ->call('addToFolder', $receipt->id, $folder->id);

    expect($folder->fresh()->scores()->pluck('scores.id')->all())->toBe([$borrowed->id]);
});

it('refuses to file a score whose loan has ended', function () {
    $lender = User::factory()->create();
    $borrower = User::factory()->create();

    $borrowed = Score::factory()->create(['user_id' => $lender->id]);
    $loan = Loan::factory()->of($borrowed)->create();
    $receipt = app(LoanKeepingService::class)->keep($loan, $borrower);
    $loan->revoke();

    $folder = \App\Models\Folder::factory()->create(['user_id' => $borrower->id]);

    actingAs($borrower);

    Livewire::test(\App\Livewire\Pages\Loans::class)
        ->call('addToFolder', $receipt->id, $folder->id);

    expect($folder->fresh()->scores()->count())->toBe(0);
});

it('refuses to file into somebody else\'s folder', function () {
    $lender = User::factory()->create();
    $borrower = User::factory()->create();
    $stranger = User::factory()->create();

    $borrowed = Score::factory()->create(['user_id' => $lender->id]);
    $receipt = app(LoanKeepingService::class)->keep(Loan::factory()->of($borrowed)->create(), $borrower);

    $theirFolder = \App\Models\Folder::factory()->create(['user_id' => $stranger->id]);

    actingAs($borrower);

    expect(fn () => Livewire::test(\App\Livewire\Pages\Loans::class)
        ->call('addToFolder', $receipt->id, $theirFolder->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
