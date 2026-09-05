<?php

use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\ScoreView;
use App\Models\Loan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Js;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// These tests are about what a lending link opens, not about who may open it.
// The Turnstile gate in front of every one of them has its own test file.
beforeEach(function () {
    passHumanCheck();
});

it('returns 404 for an unknown token', function () {
    get(route('score.loan', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();
});

it('redirects the owner to the edit screen', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->of($score)->create();

    actingAs($user);

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertRedirect(route('scores.edit', ['score' => $score->id]));
});

it('shows read-only view to a guest', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    get(route('score.loan', ['token' => $loan->token]))->assertOk();
});

it('shows read-only view to another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    actingAs($other);

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertSet('title', $score->title)
        ->assertSet('content', $score->content)
        ->assertSet('format', $score->format->value);
});

it('loads score content into the view component', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $owner->id,
        'title' => 'My Hymn',
        'format' => 'abc',
    ]);
    $loan = Loan::factory()->of($score)->create();

    Livewire::test(ScoreView::class, ['token' => $loan->token])
        ->assertSet('title', 'My Hymn')
        ->assertSet('format', 'abc');
});

it('owner can generate a secret link', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect($score->loanToken())->toBeNull();

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('lendByLink')
        ->assertHasNoErrors();

    expect($score->fresh()->loanToken())->not->toBeNull()->toHaveLength(32);
});

it('secret link resolves to a valid URL after generation', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('lendByLink');

    $token = $score->fresh()->loanToken();
    expect($token)->not->toBeNull();

    // Verify as guest (owner would be redirected to edit)
    \Illuminate\Support\Facades\Auth::logout();
    get(route('score.loan', ['token' => $token]))->assertOk();
});

it('generating a secret link twice reuses the live grant', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('lendByLink');
    $first = $score->fresh()->loanToken();

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('lendByLink');

    expect($score->fresh()->loanToken())->toBe($first)
        ->and($score->loans()->count())->toBe(1);
});

it('owner can delete the secret link', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    Loan::factory()->of($score)->create();
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('recallLoan')
        ->assertHasNoErrors();

    expect($score->fresh()->loanToken())->toBeNull();
});

it('deleted secret link returns 404', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $token = Loan::factory()->of($score)->create()->token;
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('recallLoan');

    get(route('score.loan', ['token' => $token]))->assertNotFound();
});

it('returns 404 for a revoked or expired grant', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);

    $revoked = Loan::factory()->of($score)->revoked()->create();
    $expired = Loan::factory()->of($score)->expired()->create();

    get(route('score.loan', ['token' => $revoked->token]))->assertNotFound();
    get(route('score.loan', ['token' => $expired->token]))->assertNotFound();
});

it('another user cannot open ScoreEditor for someone elses score', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    actingAs($other);

    // ScoreEditor enforces authorization in mount, so the component itself is forbidden
    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertForbidden();
});

it('read-only view has noindex meta tag', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    get(route('score.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});

it('shows the lend button in the editor header for a links-only score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $user->id,
        'format' => null,
        'content' => null,
    ]);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSet('linksOnly', true)
        ->assertSeeHtml('openShareModal()')
        ->assertSee(__('Lend this score'));
});

it('disables the header lend button until the score is saved', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(ScoreEditor::class)
        ->assertSee(__('Save the score first'))
        ->assertSeeHtml('openShareModal()');
});

it('omits the url-encoded share section from the modal for links-only scores', function () {
    $user = User::factory()->create();
    $inline = Score::factory()->create(['user_id' => $user->id, 'format' => 'abc']);
    $linksOnly = Score::factory()->create([
        'user_id' => $user->id,
        'format' => null,
        'content' => null,
    ]);
    actingAs($user);

    $encodedBlurb = __('This link encodes the full score and all settings directly in the URL — no account or registration needed. Anyone with the link can open and preview the score instantly.');

    Livewire::test(ScoreEditor::class, ['score' => $inline])
        ->assertSee($encodedBlurb);

    Livewire::test(ScoreEditor::class, ['score' => $linksOnly])
        ->assertDontSee($encodedBlurb)
        ->assertSee(__('Lending Link'));
});

it('labels the export menu on the read-only share view', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($score)->create();

    get(route('score.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertSee('exportText: '.Js::from(__('Export')), false)
        ->assertSee('exportPdfText: '.Js::from(__('Export PDF')), false)
        ->assertSee('x-ref="abcPreview" class="min-h-16 space-y-4" wire:ignore', false);
});
