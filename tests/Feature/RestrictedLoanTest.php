<?php

use App\Livewire\Pages\LoanRecipients;
use App\Livewire\Pages\Loans;
use App\Livewire\Pages\ScoreView;
use App\Models\Folder;
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
use function Pest\Laravel\get;

/**
 * A loan restricted to named people opens for them and the lender only, never for
 * a guest, and never travels on in the recipients' own loans.
 */
it('opens a restricted loan for a named recipient', function () {
    $recipient = User::factory()->create();
    $score = Score::factory()->create(['title' => 'Csak neked']);
    $loan = Loan::factory()->of($score)->restrictedTo([$recipient])->create();

    actingAs($recipient);

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertOk()
        ->assertSee('Csak neked');
});

it('sends a guest to sign in', function () {
    passHumanCheck();

    $loan = Loan::factory()->restrictedTo([User::factory()->create()])->create();

    get(route('score.loan', ['token' => $loan->token]))->assertRedirect(route('login'));
});

it('refuses a signed-in reader who is not on the list', function () {
    $loan = Loan::factory()->restrictedTo([User::factory()->create()])->create();

    actingAs(User::factory()->create());

    get(route('score.loan', ['token' => $loan->token]))->assertForbidden();
});

it('refuses everyone but the lender when nobody is listed', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->restrictedTo()->create();

    actingAs(User::factory()->create());
    get(route('folder.loan', ['token' => $loan->token]))->assertForbidden();

    actingAs($owner);
    expect(app(LoanAccessService::class)->resolve($loan->token)?->is($loan))->toBeTrue();
});

it('ends what a recipient kept once they are taken off the list', function () {
    $recipient = User::factory()->create();
    $score = Score::factory()->create();
    $loan = Loan::factory()->of($score)->restrictedTo([$recipient])->create();

    app(LoanKeepingService::class)->keep($loan, $recipient);

    expect(app(LoanAccessService::class)->keptScoreIds($recipient))->toBe([$score->id]);

    $loan->recipients()->detach($recipient);

    expect(app(LoanAccessService::class)->keptScoreIds($recipient))->toBe([]);
});

it('ends what a stranger kept once the loan is restricted', function () {
    $stranger = User::factory()->create();
    $score = Score::factory()->create();
    $loan = Loan::factory()->of($score)->create();

    app(LoanKeepingService::class)->keep($loan, $stranger);

    $loan->forceFill(['restricted' => true])->save();

    expect(app(LoanAccessService::class)->keptScoreIds($stranger))->toBe([]);
});

it('does not let a recipient pass a restricted loan on', function () {
    $marta = User::factory()->create();
    $bela = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $marta->id]);
    $score = Score::factory()->create(['user_id' => $marta->id, 'music_id' => $music->id]);

    $martaLoan = Loan::factory()->of($score)->restrictedTo([$bela])->create();
    app(LoanKeepingService::class)->keep($martaLoan, $bela);

    $plan = MusicPlan::factory()->create(['user_id' => $bela->id]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);
    $belaLoan = Loan::factory()->of($plan)->create();

    $folder = Folder::factory()->create(['user_id' => $bela->id]);
    $folder->scores()->attach($score);
    $folderLoan = Loan::factory()->of($folder)->create();

    $access = app(LoanAccessService::class);

    expect($access->keptScoreIds($bela))->toBe([$score->id])
        ->and($access->passableScoreIds($bela))->toBe([])
        ->and($access->grantsScore($belaLoan, $score))->toBeFalse()
        ->and($access->grantsScore($folderLoan, $score))->toBeFalse();
});

it('lets the lender restrict a loan and add people by email', function () {
    $owner = User::factory()->create();
    $friend = User::factory()->create(['email' => 'barat@example.com']);
    $loan = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->create();

    actingAs($owner);

    Livewire::test(LoanRecipients::class, ['loan' => $loan])
        ->set('restricted', true)
        ->set('email', 'Barat@Example.com')
        ->call('addByEmail')
        ->assertHasNoErrors()
        ->assertSet('email', '');

    $loan->refresh();

    expect($loan->restricted)->toBeTrue()
        ->and($loan->recipients->modelKeys())->toBe([$friend->id]);
});

it('says so when no one is registered with the email', function () {
    $owner = User::factory()->create();
    $loan = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->create();

    actingAs($owner);

    Livewire::test(LoanRecipients::class, ['loan' => $loan])
        ->set('email', 'senki@example.com')
        ->call('addByEmail')
        ->assertHasErrors('email');

    expect($loan->recipients()->count())->toBe(0);
});

it('offers people who already opened the lender\'s loans, and only them', function () {
    $owner = User::factory()->create();
    $opener = User::factory()->create();
    $stranger = User::factory()->create();
    $loan = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->create();
    $other = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->create();

    ReceivedLoan::factory()->create(['loan_id' => $other->id, 'user_id' => $opener->id]);

    actingAs($owner);

    Livewire::test(LoanRecipients::class, ['loan' => $loan])
        ->call('add', $opener->id)
        ->assertOk()
        ->call('add', $stranger->id)
        ->assertNotFound();

    expect($loan->recipients()->pluck('users.id')->all())->toBe([$opener->id]);
});

it('removes a recipient', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $loan = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))->restrictedTo([$recipient])->create();

    actingAs($owner);

    Livewire::test(LoanRecipients::class, ['loan' => $loan])->call('remove', $recipient->id);

    expect($loan->recipients()->count())->toBe(0)
        ->and($loan->fresh()->restricted)->toBeTrue();
});

it('refuses the recipients screen to anyone but the lender', function () {
    $loan = Loan::factory()->create();

    actingAs(User::factory()->create());

    Livewire::test(LoanRecipients::class, ['loan' => $loan])->assertNotFound();
});

it('shows on the lent tab who a loan is for', function () {
    $owner = User::factory()->create();
    $loan = Loan::factory()->of(Score::factory()->create(['user_id' => $owner->id]))
        ->restrictedTo(User::factory()->count(2)->create())
        ->create();

    actingAs($owner);

    Livewire::test(Loans::class, ['tab' => Loans::TAB_LENT])
        ->set('tab', Loans::TAB_LENT)
        ->assertSee(route('loans.recipients', ['loan' => $loan->id]));
});
