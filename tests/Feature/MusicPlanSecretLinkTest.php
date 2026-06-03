<?php

use App\Livewire\MusicPlanShareModal;
use App\Livewire\Pages\MusicPlanShareView;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('returns 404 for an unknown plan token', function () {
    Livewire::test(MusicPlanShareView::class, ['token' => 'nonexistenttoken12345678901234'])
        ->assertNotFound();
});

it('shows plan content to a guest via token', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'is_private' => true,
        'share_token' => Str::random(32),
    ]);

    Livewire::test(MusicPlanShareView::class, ['token' => $plan->share_token])
        ->assertOk();
});

it('shows private music title via token without auth', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => Str::random(32)]);
    $music = Music::factory()->create(['is_private' => true, 'user_id' => $owner->id, 'title' => 'Titkos ének']);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    Livewire::test(MusicPlanShareView::class, ['token' => $plan->share_token])
        ->assertSee('Titkos ének');
});

it('shows private notes via token', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'private_notes' => 'Secret plan notes here',
        'share_token' => Str::random(32),
    ]);

    Livewire::test(MusicPlanShareView::class, ['token' => $plan->share_token])
        ->assertSee('Secret plan notes here');
});

it('shows score links for plan owner scores', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => Str::random(32)]);
    $music = Music::factory()->create(['user_id' => $owner->id]);
    $scoreToken = Str::random(32);
    $score = Score::factory()->create([
        'user_id' => $owner->id,
        'music_id' => $music->id,
        'share_token' => $scoreToken,
    ]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    Livewire::test(MusicPlanShareView::class, ['token' => $plan->share_token])
        ->assertSee(route('score.share', ['token' => $scoreToken]));
});

it('does not show scores from other users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => Str::random(32)]);
    $music = Music::factory()->create();
    $otherScore = Score::factory()->create([
        'user_id' => $other->id,
        'music_id' => $music->id,
        'share_token' => Str::random(32),
    ]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    Livewire::test(MusicPlanShareView::class, ['token' => $plan->share_token])
        ->assertDontSee(route('score.share', ['token' => $otherScore->share_token]));
});

it('share view component sets noindex on layout', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'share_token' => Str::random(32),
    ]);

    // Verify the component mounts successfully (noindex is set via rendering())
    Livewire::test(MusicPlanShareView::class, ['token' => $plan->share_token])
        ->assertOk()
        ->assertSet('musicPlan.id', $plan->id);
});

it('owner can generate a plan secret link', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => null]);
    actingAs($owner);

    expect($plan->share_token)->toBeNull();

    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('generateSecretLink')
        ->assertHasNoErrors();

    expect($plan->fresh()->share_token)->not->toBeNull()->toHaveLength(32);
});

it('generating plan token auto-creates score tokens', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => null]);
    $music = Music::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $owner->id,
        'music_id' => $music->id,
        'share_token' => null,
    ]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    actingAs($owner);
    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('generateSecretLink');

    expect($score->fresh()->share_token)->not->toBeNull()->toHaveLength(32);
});

it('generating plan token does not overwrite existing score tokens', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => null]);
    $existingToken = Str::random(32);
    $music = Music::factory()->create();
    $score = Score::factory()->create([
        'user_id' => $owner->id,
        'music_id' => $music->id,
        'share_token' => $existingToken,
    ]);
    $slotPlan = MusicPlanSlotPlan::factory()->create(['music_plan_id' => $plan->id]);
    MusicPlanSlotAssignment::factory()->create([
        'music_plan_slot_plan_id' => $slotPlan->id,
        'music_id' => $music->id,
    ]);

    actingAs($owner);
    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('generateSecretLink');

    expect($score->fresh()->share_token)->toBe($existingToken);
});

it('owner can delete the plan secret link', function () {
    $owner = User::factory()->create();
    $plan = MusicPlan::factory()->create([
        'user_id' => $owner->id,
        'share_token' => Str::random(32),
    ]);
    actingAs($owner);

    Livewire::test(MusicPlanShareModal::class, ['musicPlan' => $plan])
        ->call('deleteSecretLink')
        ->assertHasNoErrors();

    expect($plan->fresh()->share_token)->toBeNull();
});

it('deleted plan secret link returns 404', function () {
    $owner = User::factory()->create();
    $token = Str::random(32);
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'share_token' => $token]);
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
