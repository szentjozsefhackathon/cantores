<?php

use App\Livewire\Pages\FolderView;
use App\Livewire\Pages\Loans;
use App\Livewire\Pages\ScoreView;
use App\Models\Folder;
use App\Models\Loan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use App\Services\LoanKeepingService;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * The borrower's side of a loan: a link from March lived in an inbox, and this is
 * what puts it on the site instead.
 */
it('records a signed-in open without keeping anything', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    actingAs($borrower);

    Livewire::test(ScoreView::class, ['token' => $loan->token])->assertOk();

    $receipt = ReceivedLoan::query()->where('user_id', $borrower->id)->first();

    expect($receipt)->not->toBeNull()
        ->and($receipt->loan_id)->toBe($loan->id)
        ->and($receipt->kept_at)->toBeNull()
        ->and($loan->fresh()->open_count)->toBe(1);
});

it('leaves a signed-out open anonymous but counted', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    Livewire::test(ScoreView::class, ['token' => $loan->token])->assertOk();

    expect(ReceivedLoan::query()->count())->toBe(0)
        ->and($loan->fresh()->open_count)->toBe(1);
});

it('records nothing when the owner follows their own link', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    actingAs($owner);

    Livewire::test(FolderView::class, ['token' => $loan->token]);

    expect(ReceivedLoan::query()->count())->toBe(0);
});

it('saves a borrowed score into the lending centre', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id, 'title' => 'Ó jöjj, ó jöjj']);
    $loan = Loan::factory()->of($score)->create();

    actingAs($borrower);

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertSet('kept', false)
        ->call('keep')
        ->assertSet('kept', true);

    expect(ReceivedLoan::query()->kept()->where('user_id', $borrower->id)->count())->toBe(1);

    Livewire::test(Loans::class)->assertSee('Ó jöjj, ó jöjj');
});

it('tells the borrower whose score it is and that the lender sees the open', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    actingAs($borrower);

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertSet('ownerName', $owner->displayName)
        ->assertSee(__('The lender can see that you opened it.'));
});

it('keeps a kept row out of the list once it is hidden', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id, 'title' => 'Elrejtett ének']);
    $loan = Loan::factory()->of($score)->create();

    $receipt = app(LoanKeepingService::class)->keep($loan, $borrower);

    actingAs($borrower);

    Livewire::test(Loans::class)
        ->assertSee('Elrejtett ének')
        ->call('hide', $receipt->id)
        ->assertDontSee('Elrejtett ének');

    // Hidden, not forgotten: the lender's reach figures still count the open.
    expect($receipt->fresh()->hidden_at)->not->toBeNull();
});

it('marks a kept loan as ended once the owner recalls it', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id, 'title' => 'Visszakért ének']);
    $loan = Loan::factory()->of($score)->create();

    app(LoanKeepingService::class)->keep($loan, $borrower);
    $loan->revoke();

    actingAs($borrower);

    Livewire::test(Loans::class)
        ->assertSee('Visszakért ének')
        ->assertSee(__('Recalled'))
        ->assertSee(__('Ask again'));
});

it('never lets a kept row grant access on its own', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    app(LoanKeepingService::class)->keep($loan, $borrower);
    $loan->revoke();

    actingAs($borrower);

    Livewire::test(ScoreView::class, ['token' => $loan->token])->assertNotFound();
});

it('reports reach without implying the named openers are everyone', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    // One named open, three anonymous ones.
    actingAs($borrower);
    Livewire::test(ScoreView::class, ['token' => $loan->token]);

    auth()->logout();
    foreach (range(1, 3) as $ignored) {
        Livewire::test(ScoreView::class, ['token' => $loan->token]);
    }

    $reach = app(LoanKeepingService::class)->reach($loan->fresh());

    expect($reach['opens'])->toBe(4)
        ->and($reach['known'])->toBe(1)
        ->and($reach['anonymous'])->toBe(3);
});

it('notifies the owner when a borrower asks for an ended loan again', function () {
    $owner = User::factory()->create();
    $borrower = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    $receipt = app(LoanKeepingService::class)->keep($loan, $borrower);
    $loan->revoke();

    actingAs($borrower);

    Livewire::test(Loans::class)->call('askAgain', $receipt->id);

    expect($owner->fresh()->unread_notifications_count)->toBe(1);
});
