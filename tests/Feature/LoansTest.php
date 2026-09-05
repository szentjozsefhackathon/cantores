<?php

use App\Livewire\Pages\Loans;
use App\Models\Folder;
use App\Models\Loan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// What the link opens, not who may open it — the Turnstile gate is tested apart.
beforeEach(function () {
    passHumanCheck();
});

it('requires authentication', function () {
    get(route('loans'))->assertRedirect();
});

it('lists the live links the user has handed out', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'title' => 'Adventi ének']);
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Advent']);

    Loan::factory()->of($score)->create();
    Loan::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(Loans::class)
        ->call('selectTab', 'lent')
        ->assertSee('Adventi ének')
        ->assertSee('Advent');
});

it('hides revoked and expired links', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'title' => 'Régi ének']);

    Loan::factory()->of($score)->revoked()->create();
    Loan::factory()->of($score)->expired()->create();

    actingAs($user);

    Livewire::test(Loans::class)->call('selectTab', 'lent')->assertDontSee('Régi ének');
});

it('does not list links belonging to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherScore = Score::factory()->create(['user_id' => $other->id, 'title' => 'Idegen ének']);
    Loan::factory()->of($otherScore)->create();

    actingAs($user);

    Livewire::test(Loans::class)->call('selectTab', 'lent')->assertDontSee('Idegen ének');
});

it('revokes a link and closes the URL it opened', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->of($score)->create();

    get(route('score.loan', ['token' => $loan->token]))->assertOk();

    actingAs($user);

    Livewire::test(Loans::class)
        ->call('revoke', $loan->id)
        ->assertHasNoErrors();

    expect($loan->fresh()->isLive())->toBeFalse();

    \Illuminate\Support\Facades\Auth::logout();
    get(route('score.loan', ['token' => $loan->token]))->assertNotFound();
});

it('revoking a folder link closes the scores it reached', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $folder->scores()->attach($score);
    $loan = Loan::factory()->of($folder)->create();

    get($score->loanUrl($loan->token))->assertOk();

    actingAs($user);
    Livewire::test(Loans::class)->call('revoke', $loan->id);
    \Illuminate\Support\Facades\Auth::logout();

    get($score->loanUrl($loan->token))->assertNotFound();
});

it('cannot revoke another users link', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherScore = Score::factory()->create(['user_id' => $other->id]);
    $loan = Loan::factory()->of($otherScore)->create();

    actingAs($user);

    expect(fn () => Livewire::test(Loans::class)->call('revoke', $loan->id))
        ->toThrow(ModelNotFoundException::class);

    expect($loan->fresh()->isLive())->toBeTrue();
});

it('backfills legacy share tokens into grants, preserving the token', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'share_token' => 'legacyscoretoken0123456789012345']);
    $folder = Folder::factory()->create(['user_id' => $user->id, 'share_token' => 'legacyfoldertoken012345678901234']);

    Loan::query()->delete();

    $migration = require database_path('migrations/2026_08_31_151349_backfill_shares_from_share_tokens.php');
    $migration->up();

    expect(Loan::query()->where('token', 'legacyscoretoken0123456789012345')->first())
        ->not->toBeNull()
        ->lendable_type->toBe(Score::class)
        ->lendable_id->toBe($score->id)
        ->user_id->toBe($user->id);

    expect(Loan::query()->where('token', 'legacyfoldertoken012345678901234')->first())
        ->not->toBeNull()
        ->lendable_type->toBe(Folder::class)
        ->lendable_id->toBe($folder->id);

    // and the links keep working
    get(route('score.loan', ['token' => 'legacyscoretoken0123456789012345']))->assertOk();
    get(route('folder.loan', ['token' => 'legacyfoldertoken012345678901234']))->assertOk();
});

it('shows the score editor which folder and plan links reach a score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Nagyböjt']);
    $folder->scores()->attach($score);
    $folderLoan = Loan::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(\App\Livewire\Pages\ScoreEditor::class, ['score' => $score])
        ->assertSee('Nagyböjt');

    // revoking from the editor closes the folder link, and the score with it
    Livewire::test(\App\Livewire\Pages\ScoreEditor::class, ['score' => $score])
        ->call('revokeIndirectLoan', $folderLoan->id)
        ->assertHasNoErrors();

    expect($folderLoan->fresh()->isLive())->toBeFalse();

    \Illuminate\Support\Facades\Auth::logout();
    get($score->loanUrl($folderLoan->token))->assertNotFound();
});

it('does not list a scores own link as an indirect one', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    Loan::factory()->of($score)->create();

    actingAs($user);

    expect(Livewire::test(\App\Livewire\Pages\ScoreEditor::class, ['score' => $score])->get('indirectLoans'))
        ->toHaveCount(0);
});

it('rolls up publication status across every score I offered', function () {
    $user = User::factory()->create();
    $approved = Score::factory()->create(['user_id' => $user->id, 'title' => 'Kint lévő ének']);
    $rejected = Score::factory()->create(['user_id' => $user->id, 'title' => 'Elutasított ének']);
    $someoneElses = Score::factory()->create(['title' => 'Idegen ének']);

    \App\Models\ScorePublication::factory()->of($approved)->approved()->create();
    \App\Models\ScorePublication::factory()->of($rejected)->create([
        'status' => \App\Enums\ScorePublicationStatus::Rejected,
        'review_notes' => 'Nem szabad felhasználású.',
    ]);
    \App\Models\ScorePublication::factory()->of($someoneElses)->approved()->create();

    actingAs($user);

    // Status lives inside a single score's editor otherwise, so a rejected
    // nomination is invisible until you happen to open that score.
    Livewire::test(Loans::class)
        ->call('selectTab', 'published')
        ->assertSee('Kint lévő ének')
        ->assertSee('Elutasított ének')
        ->assertSee('Nem szabad felhasználású.')
        ->assertDontSee('Idegen ének');
});

it('separates what I borrowed from what I lent', function () {
    $user = User::factory()->create();
    $lender = User::factory()->create();

    $mine = Score::factory()->create(['user_id' => $user->id, 'title' => 'Saját kottám']);
    $theirs = Score::factory()->create(['user_id' => $lender->id, 'title' => 'Kölcsönkapott kotta']);

    Loan::factory()->of($mine)->create();
    app(\App\Services\LoanKeepingService::class)->keep(Loan::factory()->of($theirs)->create(), $user);

    actingAs($user);

    Livewire::test(Loans::class)
        ->assertSee('Kölcsönkapott kotta')
        ->assertDontSee('Saját kottám')
        ->call('selectTab', 'lent')
        ->assertSee('Saját kottám')
        ->assertDontSee('Kölcsönkapott kotta');
});
