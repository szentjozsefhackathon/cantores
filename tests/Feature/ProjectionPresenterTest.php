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

/*
 * The slides left out of today's service travel beside the deck rather than
 * inside it: every slide is still engraved, and the presenter filters. That is
 * what keeps what the editor showed and what the wall shows the same slides,
 * made the same way.
 */
it('tells the projector which slides this service walks past', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'excluded_slides' => ['16/9' => [1, 3], '1/1' => [0]],
    ]);

    actingAs($user);

    $presenter = Livewire::test(ProjectionPresenter::class, ['projection' => $projection]);

    expect($presenter->get('excluded'))->toBe([$entry->id => [1, 3]]);

    // The shape decides which list applies, because the page breaks do.
    $projection->update(['ratio' => '4/3']);

    $presenter->call('reload');

    expect($presenter->get('excluded'))->toBe([]);
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

/*
 * The wall goes dark the way house lights do and comes back the way a hymn
 * board does. The black is laid over the picture rather than swapped for it, so
 * that the duration can belong to the state: `darkFadeMs` is the whole rule, and
 * it is zero in every direction except the cantor blanking the screen.
 */
it('fades the wall out and brings it back instantly', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionPresenter::class, ['projection' => $projection])
        ->assertSeeHtml('transition: `opacity ${darkFadeMs}ms ease-in`')
        ->assertSeeHtml('opacity: dark ?');
});

/*
 * The slide's box is exactly the slide's shape, whatever shape the window is. A
 * box that filled the height and only capped its width came out taller than the
 * slide in a narrow window, and a score stood on a white band where the room
 * should see black. Nor is the box white: its size is fractional, and the white
 * showed as a hairline beside the slide. The paper goes inside the slide.
 */
it('sizes the slide to its own shape rather than to the window', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionPresenter::class, ['projection' => $projection])
        ->assertSeeHtml('width: `min(100vw, 100vh * (${aspectRatio}))`')
        ->assertDontSeeHtml("height: '100%', maxWidth: '100%'")
        ->assertDontSeeHtml('x-ref="stageBox"
            class="bg-white"');
});
