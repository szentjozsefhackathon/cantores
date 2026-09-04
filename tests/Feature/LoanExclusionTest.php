<?php

use App\Livewire\Pages\FolderView;
use App\Livewire\Pages\LoanManager;
use App\Models\Folder;
use App\Models\Loan;
use App\Models\Score;
use App\Models\User;
use App\Services\LoanAccessService;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Everything is lent by default, and a score added later is lent too: a musician
 * at a service who cannot open a score because of a forgotten tick is worse than
 * one who sees a half-finished arrangement.
 */
it('lends everything in a folder when nothing has been excluded', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $first = Score::factory()->create(['user_id' => $owner->id]);
    $second = Score::factory()->create(['user_id' => $owner->id]);
    $folder->scores()->attach([$first->id, $second->id]);

    $loan = Loan::factory()->of($folder)->create();

    expect($loan->exclusions()->count())->toBe(0)
        ->and(app(LoanAccessService::class)->scoreIdsFor($loan))
        ->toEqualCanonicalizing([$first->id, $second->id]);
});

it('includes a score added to the folder after the loan was made', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    $late = Score::factory()->create(['user_id' => $owner->id]);
    $folder->scores()->attach($late);

    expect(app(LoanAccessService::class)->scoreIdsFor($loan))->toBe([$late->id]);
});

it('closes an excluded score for everyone holding the link', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $kept = Score::factory()->create(['user_id' => $owner->id, 'title' => 'Bent maradó']);
    $dropped = Score::factory()->create(['user_id' => $owner->id, 'title' => 'Kihagyott']);
    $folder->scores()->attach([$kept->id, $dropped->id]);

    $loan = Loan::factory()->of($folder)->create();

    actingAs($owner);

    Livewire::test(LoanManager::class, ['loan' => $loan])
        ->call('toggle', $dropped->id);

    expect(app(LoanAccessService::class)->scoreIdsFor($loan->fresh()))->toBe([$kept->id]);

    auth()->logout();

    Livewire::test(FolderView::class, ['token' => $loan->token])
        ->assertSee('Bent maradó')
        ->assertDontSee('Kihagyott');

    Livewire::test(\App\Livewire\Pages\ScoreView::class, ['token' => $loan->token, 'score' => $dropped])
        ->assertNotFound();
});

it('puts an excluded score back', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $folder->scores()->attach($score);

    $loan = Loan::factory()->of($folder)->create();

    actingAs($owner);

    Livewire::test(LoanManager::class, ['loan' => $loan])
        ->call('toggle', $score->id)
        ->call('toggle', $score->id);

    expect($loan->fresh()->exclusions()->count())->toBe(0)
        ->and(app(LoanAccessService::class)->scoreIdsFor($loan->fresh()))->toBe([$score->id]);
});

it('does not let a stray exclusion revoke a single-score loan', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    $loan->exclusions()->create(['score_id' => $score->id]);

    expect(app(LoanAccessService::class)->scoreIdsFor($loan->fresh()))->toBe([$score->id]);
});

it('refuses the management screen to anyone but the lender', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    actingAs($stranger);

    Livewire::test(LoanManager::class, ['loan' => $loan])->assertNotFound();
});

it('has no management screen for a single score, which has nothing to exclude', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    actingAs($owner);

    Livewire::test(LoanManager::class, ['loan' => $loan])->assertNotFound();
});
