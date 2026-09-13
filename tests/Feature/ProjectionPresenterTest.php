<?php

use App\Livewire\Pages\ProjectionPresenter;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * The presenter is the deck as the room sees it. It writes nothing — a deck is
 * finished by the time it is projected, and a presenter that could change it is
 * one that can be changed by accident — so what there is to check is that it
 * shows the right deck, to the right person, and can be told to read it again
 * without being closed.
 */

it('hands the browser the same deck the editor arranged', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:F\nF G A B|\n",
    ]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    $presenter = Livewire::test(ProjectionPresenter::class, ['projection' => $projection]);

    expect($presenter->get('geometry'))->toMatchArray(['ratio' => '16/9', 'aspectRatio' => '16/9'])
        ->and($presenter->get('entries'))->toHaveCount(1)
        ->and($presenter->get('entries')[0]['kind'])->toBe('score')
        ->and($presenter->get('entries')[0]['content'])->toContain('F G A B');
});

it('refuses to project somebody elses deck', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $owner->id]);

    actingAs($stranger);

    Livewire::test(ProjectionPresenter::class, ['projection' => $projection])->assertForbidden();
});

/*
 * A correction made in the editor on another screen reaches the projector by
 * pressing refresh, rather than by closing what is being projected and opening
 * it again.
 */
it('reads the deck again without being closed', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Advent']);

    actingAs($user);

    $presenter = Livewire::test(ProjectionPresenter::class, ['projection' => $projection])
        ->assertSet('title', 'Advent');

    $projection->update(['title' => 'Advent 1.', 'ratio' => '4/3']);

    $presenter->call('reload')
        ->assertSet('title', 'Advent 1.')
        ->assertDispatched('projection-updated');

    expect($presenter->get('geometry')['aspectRatio'])->toBe('4/3');
});

// The screen must not show what its owner may no longer read: entitlement is
// resolved here rather than carried over from whenever the deck was arranged.
it('leaves out a score the viewer can no longer read', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $theirs = Score::factory()->abc()->create(['user_id' => $stranger->id]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $theirs->id,
    ]);

    actingAs($user);

    expect(Livewire::test(ProjectionPresenter::class, ['projection' => $projection])->get('entries'))
        ->toBe([]);
});
