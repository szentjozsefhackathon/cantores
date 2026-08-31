<?php

use App\Livewire\Pages\SharedLinks;
use App\Models\Folder;
use App\Models\Score;
use App\Models\Share;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('requires authentication', function () {
    get(route('shared-links'))->assertRedirect();
});

it('lists the live links the user has handed out', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'title' => 'Adventi ének']);
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Advent']);

    Share::factory()->of($score)->create();
    Share::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(SharedLinks::class)
        ->assertSee('Adventi ének')
        ->assertSee('Advent');
});

it('hides revoked and expired links', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'title' => 'Régi ének']);

    Share::factory()->of($score)->revoked()->create();
    Share::factory()->of($score)->expired()->create();

    actingAs($user);

    Livewire::test(SharedLinks::class)->assertDontSee('Régi ének');
});

it('does not list links belonging to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherScore = Score::factory()->create(['user_id' => $other->id, 'title' => 'Idegen ének']);
    Share::factory()->of($otherScore)->create();

    actingAs($user);

    Livewire::test(SharedLinks::class)->assertDontSee('Idegen ének');
});

it('revokes a link and closes the URL it opened', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $share = Share::factory()->of($score)->create();

    get(route('score.share', ['token' => $share->token]))->assertOk();

    actingAs($user);

    Livewire::test(SharedLinks::class)
        ->call('revoke', $share->id)
        ->assertHasNoErrors();

    expect($share->fresh()->isLive())->toBeFalse();

    \Illuminate\Support\Facades\Auth::logout();
    get(route('score.share', ['token' => $share->token]))->assertNotFound();
});

it('revoking a folder link closes the scores it reached', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $folder->scores()->attach($score);
    $share = Share::factory()->of($folder)->create();

    get($score->shareUrl($share->token))->assertOk();

    actingAs($user);
    Livewire::test(SharedLinks::class)->call('revoke', $share->id);
    \Illuminate\Support\Facades\Auth::logout();

    get($score->shareUrl($share->token))->assertNotFound();
});

it('cannot revoke another users link', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherScore = Score::factory()->create(['user_id' => $other->id]);
    $share = Share::factory()->of($otherScore)->create();

    actingAs($user);

    expect(fn () => Livewire::test(SharedLinks::class)->call('revoke', $share->id))
        ->toThrow(ModelNotFoundException::class);

    expect($share->fresh()->isLive())->toBeTrue();
});

it('backfills legacy share tokens into grants, preserving the token', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id, 'share_token' => 'legacyscoretoken0123456789012345']);
    $folder = Folder::factory()->create(['user_id' => $user->id, 'share_token' => 'legacyfoldertoken012345678901234']);

    Share::query()->delete();

    $migration = require database_path('migrations/2026_08_31_151349_backfill_shares_from_share_tokens.php');
    $migration->up();

    expect(Share::query()->where('token', 'legacyscoretoken0123456789012345')->first())
        ->not->toBeNull()
        ->shareable_type->toBe(Score::class)
        ->shareable_id->toBe($score->id)
        ->user_id->toBe($user->id);

    expect(Share::query()->where('token', 'legacyfoldertoken012345678901234')->first())
        ->not->toBeNull()
        ->shareable_type->toBe(Folder::class)
        ->shareable_id->toBe($folder->id);

    // and the links keep working
    get(route('score.share', ['token' => 'legacyscoretoken0123456789012345']))->assertOk();
    get(route('folder.share', ['token' => 'legacyfoldertoken012345678901234']))->assertOk();
});

it('shows the score editor which folder and plan links reach a score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Nagyböjt']);
    $folder->scores()->attach($score);
    $folderShare = Share::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(\App\Livewire\Pages\ScoreEditor::class, ['score' => $score])
        ->assertSee('Nagyböjt');

    // revoking from the editor closes the folder link, and the score with it
    Livewire::test(\App\Livewire\Pages\ScoreEditor::class, ['score' => $score])
        ->call('revokeIndirectShare', $folderShare->id)
        ->assertHasNoErrors();

    expect($folderShare->fresh()->isLive())->toBeFalse();

    \Illuminate\Support\Facades\Auth::logout();
    get($score->shareUrl($folderShare->token))->assertNotFound();
});

it('does not list a scores own link as an indirect one', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    Share::factory()->of($score)->create();

    actingAs($user);

    expect(Livewire::test(\App\Livewire\Pages\ScoreEditor::class, ['score' => $score])->get('indirectShares'))
        ->toHaveCount(0);
});
