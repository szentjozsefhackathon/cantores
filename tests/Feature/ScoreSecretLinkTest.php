<?php

use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\ScoreView;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('returns 404 for an unknown token', function () {
    get(route('score.share', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();
});

it('redirects the owner to the edit screen', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'share_token' => 'ownertoken1234567890123456789012']);

    actingAs($user);

    Livewire::test(ScoreView::class, ['token' => $score->share_token])
        ->assertRedirect(route('scores.edit', ['score' => $score->id]));
});

it('shows read-only view to a guest', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id, 'share_token' => 'guesttoken12345678901234567890ab']);

    get(route('score.share', ['token' => $score->share_token]))->assertOk();
});

it('shows read-only view to another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id, 'share_token' => 'othertoken1234567890123456789012']);

    actingAs($other);

    Livewire::test(ScoreView::class, ['token' => $score->share_token])
        ->assertSet('title', $score->title)
        ->assertSet('content', $score->content)
        ->assertSet('format', $score->format->value);
});

it('loads score content into the view component', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $owner->id,
        'share_token' => 'contenttoken123456789012345678ab',
        'title' => 'My Hymn',
        'format' => 'abc',
    ]);

    Livewire::test(ScoreView::class, ['token' => $score->share_token])
        ->assertSet('title', 'My Hymn')
        ->assertSet('format', 'abc');
});

it('owner can generate a secret link', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect($score->share_token)->toBeNull();

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('generateSecretLink')
        ->assertHasNoErrors();

    expect($score->fresh()->share_token)->not->toBeNull()->toHaveLength(32);
});

it('secret link resolves to a valid URL after generation', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('generateSecretLink');

    $token = $score->fresh()->share_token;
    expect($token)->not->toBeNull();

    // Verify as guest (owner would be redirected to edit)
    \Illuminate\Support\Facades\Auth::logout();
    get(route('score.share', ['token' => $token]))->assertOk();
});

it('owner can delete the secret link', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'share_token' => 'deletetoken12345678901234567890']);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteSecretLink')
        ->assertHasNoErrors();

    expect($score->fresh()->share_token)->toBeNull();
});

it('deleted secret link returns 404', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'share_token' => 'deletedtoken1234567890123456789']);
    $token = $score->share_token;
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteSecretLink');

    get(route('score.share', ['token' => $token]))->assertNotFound();
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
    $score = Score::factory()->create(['user_id' => $owner->id, 'share_token' => 'noindextoken1234567890123456789a']);

    get(route('score.share', ['token' => $score->share_token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});
