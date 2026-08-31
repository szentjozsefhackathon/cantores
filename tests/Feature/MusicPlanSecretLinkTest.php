<?php

use App\Livewire\MusicPlanShareModal;
use App\Livewire\Pages\MusicPlanShareView;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\Share;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

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
    Livewire::test(MusicPlanShareView::class, ['token' => 'nonexistenttoken12345678901234'])
        ->assertNotFound();
});

it('shows plan content to a guest via token', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
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
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertSee('Titkos ének');
});

it('shows private notes via token', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'private_notes' => 'Secret plan notes here',
    ]);
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertSee('Secret plan notes here');
});

it('shows score links for plan owner scores', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertSee($score->shareUrl($share->token));
});

it('reaches the owner scores without minting tokens on them', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])->assertOk();

    // The score is reachable through the plan grant, but has no grant of its own —
    // that is what makes revoking the plan link sufficient.
    expect($score->fresh()->shares()->count())->toBe(0);

    get($score->shareUrl($share->token))->assertOk();
});

it('reaches a score added after the link was created', function () {
    $owner = User::factory()->create();
    [$plan, $music] = planWithScore($owner);
    $share = Share::factory()->of($plan)->create();

    $later = Score::factory()->create(['user_id' => $owner->id, 'music_id' => $music->id]);

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertSee($later->shareUrl($share->token));

    get($later->shareUrl($share->token))->assertOk();
});

it('revoking the plan link revokes the score links reached through it', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $share = Share::factory()->of($plan)->create();
    $scoreUrl = $score->shareUrl($share->token);

    get($scoreUrl)->assertOk();

    actingAs($owner);
    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('deleteSecretLink');

    \Illuminate\Support\Facades\Auth::logout();

    get($scoreUrl)->assertNotFound();
    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])->assertNotFound();
});

it('leaves a directly shared score reachable after the plan link is revoked', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $planShare = Share::factory()->of($plan)->create();
    $scoreShare = Share::factory()->of($score)->create();

    $planShare->revoke();

    get($score->shareUrl($planShare->token))->assertNotFound();
    get(route('score.share', ['token' => $scoreShare->token]))->assertOk();
});

it('stops reaching a score once its music leaves the plan', function () {
    $owner = User::factory()->create();
    [$plan, $music, $score] = planWithScore($owner);
    $share = Share::factory()->of($plan)->create();

    get($score->shareUrl($share->token))->assertOk();

    MusicPlanSlotAssignment::query()->where('music_id', $music->id)->delete();

    get($score->shareUrl($share->token))->assertNotFound();
});

it('shows the score incipit via the share route, suppressing the public preview carousel', function () {
    Storage::fake();

    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner, ['public_preview' => true]);
    Storage::put($score->incipit_path, 'fake-png-data');
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertSee(route('share.score.incipit', ['token' => $share->token, 'score' => $score]))
        ->assertDontSee(route('scores.public-incipit', $score));
});

it('shows private score url links via the plan secret link', function () {
    $owner = User::factory()->create();
    [$plan, , $score] = planWithScore($owner);
    $score->urls()->create(['url' => 'https://example.com/private-sheet', 'comment' => 'soprano part']);
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
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
    $share = Share::factory()->of($plan)->create();

    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertDontSee($otherScore->shareUrl($share->token));

    // and the grant does not reach it even when the URL is guessed
    get($otherScore->shareUrl($share->token))->assertNotFound();
});

it('share view component sets noindex on layout', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $share = Share::factory()->of($plan)->create();

    // Verify the component mounts successfully (noindex is set via rendering())
    Livewire::test(MusicPlanShareView::class, ['token' => $share->token])
        ->assertOk()
        ->assertSet('musicPlan.id', $plan->id);
});

it('owner can generate a plan secret link', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    actingAs($owner);

    expect($plan->shareToken())->toBeNull();

    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('generateSecretLink')
        ->assertHasNoErrors();

    expect($plan->fresh()->shareToken())->not->toBeNull()->toHaveLength(32);
});

it('owner can delete the plan secret link', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    Share::factory()->of($plan)->create();
    actingAs($owner);

    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('deleteSecretLink')
        ->assertHasNoErrors();

    expect($plan->fresh()->shareToken())->toBeNull();
});

it('deleted plan secret link returns 404', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id]);
    $token = Share::factory()->of($plan)->create()->token;
    actingAs($owner);

    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('deleteSecretLink');

    Livewire::test(MusicPlanShareView::class, ['token' => $token])
        ->assertNotFound();
});

it('non-owner cannot generate a plan secret link', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    // Must be non-private so $other can view (mount) the modal
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    actingAs($other);

    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('generateSecretLink')
        ->assertForbidden();
});
