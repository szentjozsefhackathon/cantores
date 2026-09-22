<?php

use App\Enums\ProjectionTextTheme;
use App\Livewire\Pages\PlanDocuments;
use App\Livewire\Pages\ProjectionEditor;
use App\Livewire\Projection\SlideRow;
use App\Models\Booklet;
use App\Models\MusicPlan;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\User;
use App\Support\BookletSettingFields;
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
 * Where a screen of words breaks is the browser's answer, read off the Markdown
 * every time the deck is drawn, exactly as a score's `%pagebreak` is. So the
 * payload has one job here: hand the text over untouched. Anything that stripped
 * or rewrote a break on the way out would settle on Tuesday a question that has
 * to be asked again at whatever shape the deck is thrown at on Sunday.
 */
it('hands a screens page breaks to the browser untouched', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $markdown = "Álljunk fel.\n%pagebreak169?\nÜljünk le.\n%pagebreak\nImádkozzunk.";

    ProjectionSlide::factory()->text($markdown)->create([
        'projection_id' => $projection->id,
        'sequence' => 0,
    ]);

    actingAs($user);

    $payload = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->get('renderPayload');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['kind'])->toBe('text')
        ->and($payload[0]['text'])->toBe($markdown);
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
 * numbers clamped — and filed under the screen shape it was adjusted against.
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

    expect($stored)->toHaveKey('16/9');

    $bucket = $stored['16/9'];

    expect($bucket)->toHaveKeys(['abcLyricSize', 'abcStemWidth'])
        ->and($bucket)->not->toHaveKey('somethingInvented')
        // Belongs to another format's panel, so it is not this row's to keep.
        ->and($bucket)->not->toHaveKey('gabcLayoutWidth')
        ->and($bucket['abcLyricSize'])->toEqual(120)
        ->and($bucket['abcStemWidth'])->toEqual(2.0);
});

/*
 * The point of scoping an adjustment to a shape: 70 points of lyric on a
 * widescreen is not a decision about a square screen, and a deck that changed
 * shape used to carry the first answer into the second and wreck it.
 */
it('keeps one screen shapes adjustments out of another shapes', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleScore', $score->id);

    $entry = $projection->entries()->firstOrFail();

    $editor->call('saveOverride', $entry->id, ['abcLyricSize' => 40]);

    $editor->set('ratio', '1/1')
        ->call('saveOverride', $entry->id, ['abcLyricSize' => 20]);

    $stored = $entry->fresh()->settings_override;

    expect($stored['16/9']['abcLyricSize'])->toEqual(40)
        ->and($stored['1/1']['abcLyricSize'])->toEqual(20);

    // ...and resetting one leaves the other exactly where its author put it.
    $editor->call('resetOverride', $entry->id);

    $stored = $entry->fresh()->settings_override;

    expect($stored)->not->toHaveKey('1/1')
        ->and($stored['16/9']['abcLyricSize'])->toEqual(40);
});

// Only the shape being drawn travels, flat, in the same vocabulary the score's
// own layout for that shape is written in.
it('hands the browser only the adjustments for the shape it is drawing', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'settings_override' => ['16/9' => ['abcLyricSize' => 40], '1/1' => ['abcLyricSize' => 20]],
    ]);

    actingAs($user);

    $payload = Livewire::test(ProjectionEditor::class, ['projection' => $projection])->get('renderPayload');

    expect($payload[0]['override'])->toBe(['abcLyricSize' => 40.0]);

    $projection->update(['ratio' => '1/1']);

    $payload = Livewire::test(ProjectionEditor::class, ['projection' => $projection])->get('renderPayload');

    expect($payload[0]['override'])->toBe(['abcLyricSize' => 20.0]);
});

/*
 * A screen of words in a darkened church is white on black, which is what every
 * other projection in the room does. Engraved music is not offered the choice:
 * three engines draw it in ink, and a staff reversed out of black is harder to
 * read. A chord sheet is offered it, because it is words — laid out on the slide
 * rather than engraved by anybody — and takes its ink from here like the rest.
 */
it('sets a deck s words white on black until it is told otherwise', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->assertSet('textTheme', 'dark');

    expect($editor->get('geometry')['textTheme'])->toBe('dark')
        ->and($editor->get('geometry')['textPalette'])
        ->toMatchArray(['background' => '#000000', 'text' => '#ffffff']);

    $editor->set('textTheme', 'light');

    expect($projection->fresh()->text_theme)->toBe(ProjectionTextTheme::Light)
        ->and($editor->get('geometry')['textPalette'])
        ->toMatchArray(['background' => '#ffffff', 'text' => '#000000']);
});

/*
 * The two colours only a chord sheet uses. They are stated here rather than in
 * the browser so the deck, the editor's preview and — one day — an export all
 * read the same answer; resources/js/slide-palette.js is the fall-back for a
 * client that arrived before the server did.
 */
it('states what a chord and a section label are set in, for both schemes', function () {
    foreach (ProjectionTextTheme::cases() as $theme) {
        $palette = $theme->palette();

        expect($palette)->toHaveKeys(['chord', 'label'])
            ->and($palette['chord'])->not->toBe($palette['background'])
            ->and($palette['label'])->not->toBe($palette['background']);
    }

    expect(ProjectionTextTheme::Dark->palette()['chord'])
        ->not->toBe(ProjectionTextTheme::Light->palette()['chord']);
});

it('refuses a colour scheme it has never heard of', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->set('textTheme', 'chartreuse')
        ->assertHasErrors('textTheme');

    expect($projection->fresh()->text_theme)->toBe(ProjectionTextTheme::Dark);
});

/*
 * Three of a hymn's six verses on an ordinary Sunday. The slide is still cut and
 * still drawn — it is in the contact sheet, one click from coming back — and the
 * presenter walks past it.
 */
it('leaves one of a rows slides out of the service and puts it back', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('toggleSlideExclusion', $entry->id, 2);

    expect($entry->fresh()->excluded_slides)->toBe(['16/9' => [2]])
        ->and($editor->get('excluded'))->toBe([$entry->id => [2]]);

    $editor->call('toggleSlideExclusion', $entry->id, 0);

    expect($entry->fresh()->excluded_slides)->toBe(['16/9' => [0, 2]]);

    $editor->call('toggleSlideExclusion', $entry->id, 2);

    expect($entry->fresh()->excluded_slides)->toBe(['16/9' => [0]]);

    $editor->call('toggleSlideExclusion', $entry->id, 0);

    // Nothing left out, nothing stored: an empty column is the plain answer.
    expect($entry->fresh()->excluded_slides)->toBeNull()
        ->and($editor->get('excluded'))->toBe([]);
});

/*
 * `%pagebreak169` and `%pagebreak43` cut one score into different numbers of
 * screens, so "the third slide" is a different verse at a different shape.
 */
it('remembers a skipped slide against the shape it was skipped at', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'excluded_slides' => ['16/9' => [1]],
    ]);

    actingAs($user);

    $projection->update(['ratio' => '4/3']);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection]);

    expect($editor->get('excluded'))->toBe([]);

    $editor->call('toggleSlideExclusion', $entry->id, 3);

    expect($entry->fresh()->excluded_slides)->toBe(['16/9' => [1], '4/3' => [3]]);
});

it('refuses to skip a slide in somebody elses deck', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = projectionFor($owner);
    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($stranger);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])->assertForbidden();

    expect($entry->fresh()->excluded_slides)->toBeNull();
});

/*
 * See plans/score-sections.md. A row chooses which of a score's %section
 * markers it shows, and in what order — and a change to that list clears
 * excluded_slides, since the positions it names have just moved.
 */
it('adds, removes, moves and clears a rows chosen sections', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n%section 2\nc d|\n%section Refrén\ne f|\n",
    ]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
        'excluded_slides' => ['16/9' => [0]],
    ]);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('addSection', $entry->id, 1)
        ->call('addSection', $entry->id, 3)
        ->call('addSection', $entry->id, 3)
        ->assertDispatched("projection-entry-sections-changed.{$entry->id}");

    expect($entry->fresh()->sections)->toBe([1, 3, 3])
        ->and($entry->fresh()->excluded_slides)->toBeNull();

    $editor->call('moveSection', $entry->id, 0, 1);
    expect($entry->fresh()->sections)->toBe([3, 1, 3]);

    $editor->call('removeSection', $entry->id, 1);
    expect($entry->fresh()->sections)->toBe([3, 3]);

    $editor->call('clearSections', $entry->id);
    expect($entry->fresh()->sections)->toBeNull();
});

it('refuses a section number the score does not have', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n",
    ]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('addSection', $entry->id, 5);

    expect($entry->fresh()->sections)->toBeNull();
});

it('carries a rows sections through the render payload', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n%section 2\nc d|\n",
    ]);

    $entry = ProjectionSlide::factory()->withSections([2, 1])->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    $payload = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->get('renderPayload');

    expect($payload[0]['sections'])->toBe([2, 1]);
});

it('refuses to change sections in somebody elses deck', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = projectionFor($owner);
    $score = Score::factory()->abc()->create([
        'user_id' => $owner->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n",
    ]);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($stranger);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])->assertForbidden();

    expect($entry->fresh()->sections)->toBeNull();
});

it('copies a rows chosen sections when the deck is duplicated', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    ProjectionSlide::factory()->withSections([2, 1, 1])->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    $copy = $projection->duplicate();

    expect($copy->entries()->first()->sections)->toBe([2, 1, 1]);
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
        ->and(BookletSettingFields::keysFor('abc'))->not->toContain('abcStemWidth');
});

/*
 * A projection imposes nothing on the scores it gathers, so it has no face to
 * offer: each score keeps the one its author chose against this very canvas, and
 * the screen defaults are already the condensed sans a beamer wants. The knob
 * was only a way to spoil that, and a face stored by an older client goes the
 * way any other unknown key does.
 */
it('offers no face, and forgets one a slide was given', function () {
    foreach (['abc', 'gabc', 'aretino', 'chordpro'] as $format) {
        $keys = ProjectionSettingFields::keysFor($format);

        expect($keys)->not->toContain('abcLyricFont')
            ->and($keys)->not->toContain('lyricFont')
            ->and($keys)->not->toContain('aretinoTextFont')
            ->and($keys)->not->toContain('chordproFontFamily');
    }

    expect(ProjectionSettingFields::sanitize('abc', ['abcLyricFont' => 'Merriweather', 'abcLyricSize' => 40]))
        ->toBe(['abcLyricSize' => 40.0]);
});

it('lets a slide hide an ABC scores chords', function () {
    expect(ProjectionSettingFields::keysFor('abc'))->toContain('abcHideChords')
        ->and(ProjectionSettingFields::sanitize('abc', ['abcHideChords' => true]))
        ->toBe(['abcHideChords' => true]);
});

it('opens a panel of knobs with no face among them', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    actingAs($user);

    Livewire::test(SlideRow::class, ['entry' => $entry])
        ->call('adjust')
        ->assertOk()
        ->assertSee(__('Stem width'))
        ->assertDontSee(__('Font'))
        ->assertDontSee('<select', escape: false);
});

/*
 * A slide's sizes are in each engine's own units — 4.6667 abc units of lyric, a
 * staff scale of 0.75 — where one step is a step of the last decimal. The step
 * buttons move them by a share of what they read instead, or a slide that is
 * plainly too small takes thirty presses to fix.
 */
it('steps a size by a share of itself rather than by its last decimal', function () {
    foreach (['abc', 'gabc', 'aretino', 'chordpro', 'file'] as $format) {
        foreach (ProjectionSettingFields::panelFor($format) as $field) {
            if (($field['control'] ?? null) === 'step') {
                expect($field['percent'] ?? null)->toBe(10, $field['key'].' steps by its last decimal');
            }
        }
    }
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

// The same look a booklet row opens — see resources/js/score-preview.js — and
// the same markup, because a deck's row and a booklet's row are asking the same
// question about the same score.
it('opens the shared score preview on the row, reading the row out of the editor', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    actingAs($user);

    $component = Livewire::test(SlideRow::class, [
        'entry' => $entry,
        'scoreUrl' => 'https://example.test/scores/1/edit',
    ]);

    $component->assertDontSeeHtml('data-entry-preview-modal');

    $component->call('togglePreview')
        ->assertSeeHtml('data-entry-preview-modal')
        ->assertSeeHtml('data-score-preview-sheet')
        ->assertSeeHtml('scorePreview(previewEntry('.$entry->id.'), previewGeometry())')
        ->assertDontSeeHtml('<iframe');
});

// A deck has no paper of its own, so the page its scores are previewed against
// travels with the payload rather than being invented in the browser.
it('hands the editor the page one score is previewed against', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->assertSeeHtml('previewGeometry');

    expect(Booklet::previewGeometry())
        ->toMatchArray([
            'pageWidthMm' => 148.0,
            'pageHeightMm' => 210.0,
            'marginMm' => 12.0,
            'contentWidthMm' => 124.0,
        ])
        ->and(Booklet::previewGeometry()['lyricSizePt'])->toBeGreaterThan(0)
        ->and(Booklet::previewGeometry()['staffHeightMm'])->toBeGreaterThan(0);
});

it('shows no preview button or modal for a score it cannot read', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    actingAs($user);

    Livewire::test(SlideRow::class, ['entry' => $entry, 'scoreUrl' => null])
        ->assertDontSeeHtml('data-entry-preview')
        ->call('togglePreview')
        ->assertDontSeeHtml('data-entry-preview-modal');
});

it('lists only the viewers own projections', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    Projection::factory()->create(['user_id' => $mine->id, 'title' => 'Advent']);
    Projection::factory()->create(['user_id' => $theirs->id, 'title' => 'Karácsony']);

    actingAs($mine);

    Livewire::test(PlanDocuments::class)
        ->assertSee('Advent')
        ->assertDontSee('Karácsony');
});

it('starts a projection from the list and opens its editor', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->call('setNewType', 'projection')
        ->call('createFromPlan', $plan->id)
        ->assertRedirect();

    expect(Projection::query()->where('user_id', $user->id)->where('music_plan_id', $plan->id)->exists())->toBeTrue();
});

/*
 * A screen of words had no settings at all: it took a fifteenth of the slide's
 * height and a leading nailed into the renderer, and nobody could move either.
 * Both are the deck's to say now, as factors of what the slide computes from its
 * own height — so the same words read the same way at all three shapes.
 */
it('saves how large the deck sets a screen of words', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->set('textSizeScale', 1.4)
        ->set('textLineHeight', 1.1)
        ->assertHasNoErrors();

    $projection->refresh();

    expect($projection->text_size_scale)->toBe(1.4)
        ->and($projection->text_line_height)->toBe(1.1)
        ->and($projection->geometry())->toMatchArray([
            'textSizeScale' => 1.4,
            'textLineHeight' => 1.1,
        ]);
});

// Every deck thrown before this had its words at a fifteenth of the screen and a
// leading of 1.45, and none of them may move when the setting arrives.
it('leaves a deck that has said nothing about its words exactly as it was thrown', function () {
    $projection = projectionFor(User::factory()->create());

    expect($projection->geometry())->toMatchArray([
        'textSizeScale' => 1.0,
        'textLineHeight' => 1.45,
    ]);
});

// And one screen that needs to be larger than the rest says so on its own row,
// filed under the shape it was adjusted against like every other override here.
it('lets one screen of words depart from the decks own text settings', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('addText');

    $entry = $projection->entries()->firstOrFail();

    $editor->call('saveOverride', $entry->id, [
        'textSizeScale' => 1.8,
        'textLineHeight' => 0.1,
        // A knob that belongs to a score rather than to words.
        'abcLyricSize' => 40,
    ]);

    expect($entry->fresh()->settings_override)
        ->toEqual(['16/9' => ['textSizeScale' => 1.8, 'textLineHeight' => 0.8]]);

    $payload = Livewire::test(ProjectionEditor::class, ['projection' => $projection])->get('renderPayload');

    expect($payload[0]['kind'])->toBe('text')
        ->and($payload[0]['override'])->toEqual(['textSizeScale' => 1.8, 'textLineHeight' => 0.8]);
});

// A size chosen for a widescreen says nothing about a square one, words included.
it('keeps a screen of words adjusted at one shape out of the others', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('addText');

    $entry = $projection->entries()->firstOrFail();

    $editor->call('saveOverride', $entry->id, ['textSizeScale' => 1.8]);

    $square = Livewire::test(ProjectionEditor::class, ['projection' => $projection->fresh()])
        ->set('ratio', '1/1')
        ->get('renderPayload');

    expect($square[0]['override'])->toBe([]);
});

// The panel is opened from the row, so a row of words has to offer the button
// that opens it — it had none, being neither a score nor a picture.
it('offers a screen of words the same panel of knobs a score gets', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $entry = $projection->entries()->create(['text' => 'Álljunk fel.', 'sequence' => 1]);

    actingAs($user);

    $html = Livewire::test(SlideRow::class, ['entry' => $entry])
        ->call('adjust')
        ->html();

    expect(ProjectionEditor::overrideFormat($entry))->toBe('text');

    foreach (ProjectionSettingFields::panelFor('text') as $field) {
        expect($html)->toContain($field['key']);
    }
});

it('copies the deck from the editor and goes straight to the copy', function () {
    $user = User::factory()->create();
    $projection = projectionFor($user);
    $projection->update(['title' => 'Nagyterem 16:9', 'ratio' => '16/9']);
    $projection->entries()->create(['text' => 'Bevezető', 'sequence' => 1]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('duplicate')
        ->assertRedirect();

    $copy = Projection::query()->where('id', '!=', $projection->id)->where('user_id', $user->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->title)->toBe(__(':title (copy)', ['title' => 'Nagyterem 16:9']))
        ->and($copy->entries()->count())->toBe(1);
});
