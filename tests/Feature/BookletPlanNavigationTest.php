<?php

use App\Livewire\Pages\BookletEditor;
use App\Models\Booklet;
use App\Models\MusicPlan;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The two doors between a service and the booklet printed from it. Both are
 * walked in both directions during a rehearsal — a chord is fixed in the plan,
 * a page is re-laid in the booklet — so neither may be a dead end.
 */
it('opens the plan for editing from the booklet made of it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);

    Livewire::actingAs($user)
        ->test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSeeHtml('href="'.route('music-plan-editor', $plan).'"')
        ->assertSee('Open the plan');
});

it('offers only the read-only plan when the booklet is made of someone elses', function () {
    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $booklet = Booklet::factory()->create(['user_id' => $reader->id, 'music_plan_id' => $plan->id]);

    Livewire::actingAs($reader)
        ->test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSeeHtml('href="'.route('music-plan-view', $plan).'"')
        ->assertDontSeeHtml('href="'.route('music-plan-editor', $plan).'"');
});

it('says nothing about a plan in a booklet that has none', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'music_plan_id' => null]);

    Livewire::actingAs($user)
        ->test(BookletEditor::class, ['booklet' => $booklet])
        ->assertDontSee('Open the plan');
});

it('offers to start a booklet from a plan that has none yet', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    foreach ([route('music-plan-view', $plan), route('music-plan-editor', $plan)] as $url) {
        get($url)
            ->assertOk()
            ->assertSee('Füzet készítése')
            ->assertDontSee('Füzet megnyitása');
    }
});

it('opens the booklet already made from the plan, from either the view or the editor', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);

    actingAs($user);

    foreach ([route('music-plan-view', $plan), route('music-plan-editor', $plan)] as $url) {
        get($url)
            ->assertOk()
            ->assertSee('Füzet megnyitása')
            ->assertSeeHtml('href="'.route('booklets.edit', $booklet).'"')
            ->assertSee('Új füzet');
    }
});

it('lists every booklet made from the plan when there is more than one', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $first = Booklet::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id, 'title' => 'Kántorpéldány']);
    $second = Booklet::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id, 'title' => 'Népnek']);

    actingAs($user)
        ->get(route('music-plan-view', $plan))
        ->assertOk()
        ->assertSeeHtml('href="'.route('booklets.edit', $first).'"')
        ->assertSeeHtml('href="'.route('booklets.edit', $second).'"')
        ->assertSee('Kántorpéldány')
        ->assertSee('Népnek');
});

it('keeps someone elses booklet off a public plan', function () {
    $owner = User::factory()->create();
    $reader = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => false]);
    $booklet = Booklet::factory()->create(['user_id' => $owner->id, 'music_plan_id' => $plan->id]);

    actingAs($reader)
        ->get(route('music-plan-view', $plan))
        ->assertOk()
        ->assertDontSeeHtml('href="'.route('booklets.edit', $booklet).'"')
        ->assertDontSee('Füzet megnyitása');
});
