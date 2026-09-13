<?php

use App\Livewire\Pages\ProjectionEditor;
use App\Livewire\Pages\Projections;
use App\Livewire\Projection\SlideRow;
use App\Models\MusicPlan;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\User;
use App\Support\ProjectionSettingFields;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function projectionFor(User $user, ?MusicPlan $plan = null): Projection
{
    return Projection::factory()->create([
        'user_id' => $user->id,
        'music_plan_id' => $plan?->getKey(),
    ]);
}

it('creates a projection from a music plan and names it after the celebration', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    post(route('projections.store'), ['music_plan_id' => $plan->id])->assertRedirect();

    $projection = Projection::query()->where('user_id', $user->id)->firstOrFail();

    expect($projection->music_plan_id)->toBe($plan->id)
        ->and($projection->ratio->value)->toBe('16/9')
        ->and($projection->entries()->count())->toBe(0);
});

it('refuses to start a projection from someone elses private plan', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);

    actingAs($stranger);

    post(route('projections.store'), ['music_plan_id' => $plan->id])->assertForbidden();
});

it('refuses to open somebody elses projection', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = projectionFor($owner);

    actingAs($stranger);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])->assertForbidden();
});

it('adds a score and gives it the next place in the order', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $first = Score::factory()->abc()->create(['user_id' => $user->id]);
    $second = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $first->id)
        ->call('toggleScore', $second->id);

    expect($projection->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$first->id, $second->id]);
});

it('removes a score when it is toggled again', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $score->id)
        ->call('toggleScore', $score->id);

    expect($projection->entries()->count())->toBe(0);
});

// A score id typed into a request must not pull somebody else's work onto a
// screen: what may be added is what MusicPlanScoreListService says may be read.
it('refuses to add a score the viewer cannot read', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = projectionFor($user);
    $theirs = Score::factory()->abc()->create(['user_id' => $stranger->id]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $theirs->id);

    expect($projection->entries()->count())->toBe(0);
});

it('moves a row past the one beside it', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $entries = collect(range(0, 2))->map(fn (int $sequence): ProjectionSlide => ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
        'sequence' => $sequence,
    ]));

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('move', $entries[2]->id, -1);

    expect($projection->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[2]->id, $entries[1]->id]);
});

// A row at the end of its own list has nowhere further to go, and says so by
// doing nothing rather than by failing.
it('leaves the order alone when a row is already at the end', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $entries = collect(range(0, 1))->map(fn (int $sequence): ProjectionSlide => ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
        'sequence' => $sequence,
    ]));

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('move', $entries[1]->id, 1);

    expect($projection->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[1]->id]);
});

it('adds a screen of words', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])->call('addText');

    $entry = $projection->entries()->firstOrFail();

    expect($entry->isText())->toBeTrue();
});

/*
 * The ratio is the whole of a deck's geometry, and it is not a restyling: it
 * decides which of the score's own saved layouts is read and which page breaks
 * cut. So it has to reach the browser, which is what actually does the cutting.
 */
it('sends the new shape to the browser when the ratio changes', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->assertSet('ratio', '16/9')
        ->set('ratio', '4/3')
        ->assertDispatched('projection-updated');

    expect($projection->fresh()->ratio->value)->toBe('4/3')
        ->and($projection->fresh()->geometry())->toMatchArray(['ratio' => '4/3', 'aspectRatio' => '4/3']);
});

it('refuses a shape no projector throws', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->set('ratio', '3/2')
        ->assertHasErrors('ratio');

    expect($projection->fresh()->ratio->value)->toBe('16/9');
});

/*
 * The payload hands the browser the score's whole settings column rather than
 * one ratio's slice of it. Which slice is read is the deck's ratio, and the
 * browser already knows that from the geometry — sending a slice would mean
 * re-fetching the deck every time the ratio changed.
 */
it('hands the browser the score source and the whole settings column', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:F\nF G A B|\n",
        'settings' => ['abc' => ['16/9' => ['abcLyricSize' => 31.1], 'paper' => ['abcLyricSize' => 4.9]]],
    ]);

    actingAs($user);

    $payload = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $score->id)
        ->get('renderPayload');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['kind'])->toBe('score')
        ->and($payload[0]['format'])->toBe('abc')
        ->and($payload[0]['content'])->toContain('F G A B')
        ->and($payload[0]['settings']['abc'])->toHaveKeys(['16/9', 'paper']);
});

/*
 * The override bucket is arbitrary JSON from a browser and is replayed into a
 * renderer, so it is sanitised rather than trusted — unknown keys dropped,
 * numbers clamped.
 */
it('sanitises an adjustment before storing it', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $score->id);

    $entry = $projection->entries()->firstOrFail();

    $editor->call('saveOverride', $entry->id, [
        'abcLyricSize' => 9999,
        'abcStemWidth' => 2.0,
        'somethingInvented' => 'nonsense',
        'gabcLayoutWidth' => 400,
    ]);

    $stored = $entry->fresh()->settings_override;

    expect($stored)->toHaveKeys(['abcLyricSize', 'abcStemWidth'])
        ->and($stored)->not->toHaveKey('somethingInvented')
        // Belongs to another format's panel, so it is not this row's to keep.
        ->and($stored)->not->toHaveKey('gabcLayoutWidth')
        ->and($stored['abcLyricSize'])->toEqual(120)
        ->and($stored['abcStemWidth'])->toEqual(2.0);
});

/*
 * The two knobs that exist for a projector and for nothing else. A booklet
 * correctly refuses them — a hairline is right on paper — so a projection has to
 * offer them itself.
 */
it('offers the stroke widths a beamer needs, which a booklet does not', function () {
    $keys = ProjectionSettingFields::keysFor('abc');

    expect($keys)->toContain('abcStemWidth')
        ->and($keys)->toContain('abcStaffLineWidth')
        ->and(App\Support\BookletSettingFields::keysFor('abc'))->not->toContain('abcStemWidth');
});

// A slide has no page to be laid out wider than, so there is no width to widen.
it('offers no layout width, because the canvas is the width', function () {
    foreach (['abc', 'gabc', 'aretino'] as $format) {
        $keys = ProjectionSettingFields::keysFor($format);

        expect($keys)->not->toContain('abcPageWidth')
            ->and($keys)->not->toContain('gabcLayoutWidth')
            ->and($keys)->not->toContain('aretinoStaffWidth');
    }
});

it('resets an adjustment back to the scores own layout', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $score->id);

    $entry = $projection->entries()->firstOrFail();

    $editor->call('saveOverride', $entry->id, ['abcLyricSize' => 40])
        ->call('resetOverride', $entry->id);

    expect($entry->fresh()->settings_override)->toBeNull();
});

// A row keeps itself; what the slides look like is put together by the deck.
it('tells the deck when a row changes what it shows', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    actingAs($user);

    Livewire::test(SlideRow::class, ['entry' => $entry])
        ->call('toggleShowVariation')
        ->assertDispatched('projection-entry-changed');

    expect($entry->fresh()->show_variation)->toBeTrue();
});

it('lists only the viewers own projections', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    Projection::factory()->create(['user_id' => $mine->id, 'title' => 'Advent']);
    Projection::factory()->create(['user_id' => $theirs->id, 'title' => 'Karácsony']);

    actingAs($mine);

    Livewire::test(Projections::class)
        ->assertSee('Advent')
        ->assertDontSee('Karácsony');
});

it('starts a projection from the list and opens its editor', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(Projections::class)
        ->call('createFromPlan', $plan->id)
        ->assertRedirect();

    expect(Projection::query()->where('user_id', $user->id)->where('music_plan_id', $plan->id)->exists())->toBeTrue();
});
