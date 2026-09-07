<?php

use App\Livewire\MusicPlanLoanModal;
use App\Livewire\Pages\MusicPlanLoanView;
use App\Models\Loan;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// What the link opens, not who may open it — the Turnstile gate is tested apart.
beforeEach(function () {
    passHumanCheck();
});

/**
 * Attach a music to a plan and return the score the plan then reaches.
 */
function planWithScore(User $owner, array $scoreAttributes = []): array
{
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $music = Music::factory()->create(['user_id' => $owner->id]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    $score = Score::factory()->create(array_merge([
        'user_id' => $owner->id,
        'music_id' => $music->id,
    ], $scoreAttributes));

    return [$plan, $music, $score];
}

it('returns 404 for an unknown plan token', function () {
    Livewire::test(MusicPlanLoanView::class, ['token' => 'nonexistenttoken12345678901234'])
        ->assertNotFound();
});

it('shows plan content to a guest via token', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertOk();
});

it('shows private music title via token without auth', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $music = Music::factory()->create(['is_private' => true, 'user_id' => $owner->id, 'title' => 'Titkos ének']);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee('Titkos ének');
});

it('shows private notes via token', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'private_notes' => 'Secret plan notes here',
    ]);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee('Secret plan notes here');
});

it('shows score links for plan owner scores', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee($score->loanUrl($loan->token));
});

it('reaches the owner scores without minting tokens on them', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])->assertOk();

    // The score is reachable through the plan grant, but has no grant of its own —
    // that is what makes revoking the plan link sufficient.
    expect($score->fresh()->loans()->count())->toBe(0);

    get($score->loanUrl($loan->token))->assertOk();
});

it('reaches a score added after the link was created', function () {
    $owner = User::factory()->create();
    [$plan, $music] = planWithScore($owner);
    $loan = Loan::factory()->of($plan)->create();

    $later = Score::factory()->create(['user_id' => $owner->id, 'music_id' => $music->id]);

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee($later->loanUrl($loan->token));

    get($later->loanUrl($loan->token))->assertOk();
});

it('revoking the plan link revokes the score links reached through it', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $loan = Loan::factory()->of($plan)->create();
    $scoreUrl = $score->loanUrl($loan->token);

    get($scoreUrl)->assertOk();

    actingAs($owner);
    Livewire::test(MusicPlanLoanModal::class, ['musicPlan' => $plan])
        ->call('recallLoan');

    \Illuminate\Support\Facades\Auth::logout();

    get($scoreUrl)->assertNotFound();
    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])->assertNotFound();
});

it('leaves a directly shared score reachable after the plan link is revoked', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $planShare = Loan::factory()->of($plan)->create();
    $scoreShare = Loan::factory()->of($score)->create();

    $planShare->revoke();

    get($score->loanUrl($planShare->token))->assertNotFound();
    get(route('score.loan', ['token' => $scoreShare->token]))->assertOk();
});

it('stops reaching a score once its music leaves the plan', function () {
    $owner = User::factory()->create();
    [$plan, $music, $score] = planWithScore($owner);
    $loan = Loan::factory()->of($plan)->create();

    get($score->loanUrl($loan->token))->assertOk();

    MusicPlanSlotAssignment::query()->where('music_id', $music->id)->delete();

    get($score->loanUrl($loan->token))->assertNotFound();
});

it('shows the score incipit via the share route, suppressing the public preview carousel', function () {
    Storage::fake();

    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner, ['public_preview' => true]);
    Storage::put($score->incipit_path, 'fake-png-data');
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee(route('loan.score.incipit', ['token' => $loan->token, 'score' => $score]))
        ->assertDontSee(route('scores.public-incipit', $score));
});

it('shows private score url links via the plan secret link', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $score->urls()->create(['url' => 'https://example.com/private-sheet', 'comment' => 'soprano part']);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee('https://example.com/private-sheet')
        ->assertSee('example.com')
        ->assertSee('soprano part');
});

it('does not show scores from other users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $music = Music::factory()->create();
    $otherScore = Score::factory()->create(['user_id' => $other->id, 'music_id' => $music->id]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);
    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertDontSee($otherScore->loanUrl($loan->token));

    // and the grant does not reach it even when the URL is guessed
    get($otherScore->loanUrl($loan->token))->assertNotFound();
});

it('share view component sets noindex on layout', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($plan)->create();

    // Verify the component mounts successfully (noindex is set via rendering())
    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertOk()
        ->assertSet('musicPlan.id', $plan->id);
});

it('owner can generate a plan secret link', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    actingAs($owner);

    expect($plan->loanToken())->toBeNull();

    Livewire::test(MusicPlanLoanModal::class, ['musicPlan' => $plan])
        ->call('lendByLink')
        ->assertHasNoErrors();

    expect($plan->fresh()->loanToken())->not->toBeNull()->toHaveLength(32);
});

it('owner can delete the plan secret link', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    Loan::factory()->of($plan)->create();
    actingAs($owner);

    Livewire::test(MusicPlanLoanModal::class, ['musicPlan' => $plan])
        ->call('recallLoan')
        ->assertHasNoErrors();

    expect($plan->fresh()->loanToken())->toBeNull();
});

it('deleted plan secret link returns 404', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $token = Loan::factory()->of($plan)->create()->token;
    actingAs($owner);

    Livewire::test(MusicPlanLoanModal::class, ['musicPlan' => $plan])
        ->call('recallLoan');

    Livewire::test(MusicPlanLoanView::class, ['token' => $token])
        ->assertNotFound();
});

it('non-owner cannot generate a plan secret link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    // Must be non-private so $other can view (mount) the modal
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    actingAs($other);

    Livewire::test(MusicPlanLoanModal::class, ['musicPlan' => $plan])
        ->call('lendByLink')
        ->assertForbidden();
});

/**
 * The flutist's case: she opens the band leader's link, finds her part in it, and
 * writes her own setting of the same music. It belongs on the link she was given.
 */
it('shows the reader their own score for a music in the lent plan', function () {
    $owner = User::factory()->create();
    $flutist = User::factory()->create();

    [$plan, $music] = planWithScore($owner, ['title' => 'Kantor kottaja']);
    $ownScore = Score::factory()->create([
        'user_id' => $flutist->id,
        'music_id' => $music->id,
        'title' => 'Fuvolaszolam',
    ]);

    $loan = Loan::factory()->of($plan)->create();

    actingAs($flutist);

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee('Kantor kottaja')
        ->assertSee('Fuvolaszolam')
        // Hers opens in her own editor, not through somebody else's link.
        ->assertSee(route('scores.edit', ['score' => $ownScore->id]));
});

it('keeps the reader own score to themselves', function () {
    $owner = User::factory()->create();
    $flutist = User::factory()->create();
    $stranger = User::factory()->create();

    [$plan, $music] = planWithScore($owner);
    Score::factory()->create([
        'user_id' => $flutist->id,
        'music_id' => $music->id,
        'title' => 'Fuvolaszolam',
    ]);

    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertDontSee('Fuvolaszolam');

    actingAs($stranger);

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertDontSee('Fuvolaszolam');
});

it('marks which score on the link is the reader own', function () {
    $owner = User::factory()->create();
    $flutist = User::factory()->create();

    [$plan, $music] = planWithScore($owner);
    Score::factory()->create(['user_id' => $flutist->id, 'music_id' => $music->id]);

    $loan = Loan::factory()->of($plan)->create();

    actingAs($flutist);

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee(__('Your score'))
        // The page header already says whose plan it is, so his own scores are
        // not attributed line by line.
        ->assertDontSee(__("On loan · :name's score", ['name' => $owner->displayName]));
});

it('shows a published library score on the lending link', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    [$plan, $music] = planWithScore($owner);
    $published = Score::factory()->create([
        'user_id' => $stranger->id,
        'music_id' => $music->id,
        'title' => 'Szabad kotta',
    ]);
    \App\Models\ScorePublication::factory()->of($published)->approved()->create();

    $loan = Loan::factory()->of($plan)->create();

    Livewire::test(MusicPlanLoanView::class, ['token' => $loan->token])
        ->assertSee('Szabad kotta')
        // Read from the library, not through a loan that never reached it.
        ->assertSee($published->publicUrl())
        ->assertDontSee($published->loanUrl($loan->token));
});
