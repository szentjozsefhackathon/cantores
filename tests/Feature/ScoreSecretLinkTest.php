<?php

use App\Livewire\Pages\ScoreEditor;
use App\Livewire\Pages\ScoreView;
use App\Models\Score;
use App\Models\Share;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('returns 404 for an unknown token', function () {
    get(route('score.share', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();
});

it('redirects the owner to the edit screen', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $share = Share::factory()->of($score)->create();

    actingAs($user);

    Livewire::test(ScoreView::class, ['token' => $share->token])
        ->assertRedirect(route('scores.edit', ['score' => $score->id]));
});

it('shows read-only view to a guest', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $share = Share::factory()->of($score)->create();

    get(route('score.share', ['token' => $share->token]))->assertOk();
});

it('shows read-only view to another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);
    $share = Share::factory()->of($score)->create();

    actingAs($other);

    Livewire::test(ScoreView::class, ['token' => $share->token])
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
    $share = Share::factory()->of($score)->create();

    Livewire::test(ScoreView::class, ['token' => $share->token])
        ->assertSet('title', 'My Hymn')
        ->assertSet('format', 'abc');
});

it('owner can generate a secret link', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect($score->shareToken())->toBeNull();

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('generateSecretLink')
        ->assertHasNoErrors();

    expect($score->fresh()->shareToken())->not->toBeNull()->toHaveLength(32);
});

it('secret link resolves to a valid URL after generation', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('generateSecretLink');

    $token = $score->fresh()->shareToken();
    expect($token)->not->toBeNull();

    // Verify as guest (owner would be redirected to edit)
    \Illuminate\Support\Facades\Auth::logout();
    get(route('score.share', ['token' => $token]))->assertOk();
});

it('generating a secret link twice reuses the live grant', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('generateSecretLink');
    $first = $score->fresh()->shareToken();

    Livewire::test(ScoreEditor::class, ['score' => $score])->call('generateSecretLink');

    expect($score->fresh()->shareToken())->toBe($first)
        ->and($score->shares()->count())->toBe(1);
});

it('owner can delete the secret link', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    Share::factory()->of($score)->create();
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteSecretLink')
        ->assertHasNoErrors();

    expect($score->fresh()->shareToken())->toBeNull();
});

it('deleted secret link returns 404', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $token = Share::factory()->of($score)->create()->token;
    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->call('deleteSecretLink');

    get(route('score.share', ['token' => $token]))->assertNotFound();
});

it('returns 404 for a revoked or expired grant', function () {
    $owner = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $owner->id]);

    $revoked = Share::factory()->of($score)->revoked()->create();
    $expired = Share::factory()->of($score)->expired()->create();

    get(route('score.share', ['token' => $revoked->token]))->assertNotFound();
    get(route('score.share', ['token' => $expired->token]))->assertNotFound();
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
    $share = Share::factory()->of($score)->create();

    get(route('score.share', ['token' => $share->token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});
