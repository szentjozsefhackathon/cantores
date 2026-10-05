<?php

use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use App\Enums\ScoreFormat;
use App\Livewire\Pages\ProjectionEditor;
use App\Livewire\Projection\StyleEditor;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\ProjectionStyle;
use App\Models\Score;
use App\Models\User;
use App\Services\ProjectionRenderPayload;
use App\Support\ProjectionSettingFields;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Slide styles: a named description of one screen, owned by a cantor and
 * referred to by the decks shown on it.
 */
it('creates a style at the deck’s shape and puts the deck in it', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->fourThree()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(StyleEditor::class, ['projection' => $projection])
        ->call('openEditor', null)
        ->assertSet('ratio', '4/3')
        ->set('name', 'Plébánia – nagy vászon')
        ->set('minPercent', 80)
        ->call('create')
        ->assertHasNoErrors()
        ->assertDispatched('projection-style-saved', attach: true);

    $style = ProjectionStyle::query()->sole();

    expect($style->user_id)->toBe($user->id)
        ->and($style->ratio)->toBe(ProjectionRatio::FourThree)
        ->and($style->min_scale)->toBe(0.8)
        ->and($style->settings)->toBeNull();

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('styleSaved', $style->id, true);

    expect($projection->fresh()->projection_style_id)->toBe($style->id);
});

it('offers a new style a 90% shrink allowance', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(StyleEditor::class, ['projection' => $projection])
        ->call('openEditor', null)
        ->assertSet('minPercent', 90)
        ->set('name', 'Alapértelmezett')
        ->call('create');

    expect(ProjectionStyle::query()->sole()->min_scale)->toBe(0.9);
});

it('keeps style names unique per cantor, but not across cantors', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    ProjectionStyle::factory()->create(['user_id' => $user->id, 'name' => 'Kápolna']);
    ProjectionStyle::factory()->create(['user_id' => $other->id, 'name' => 'Templom']);

    actingAs($user);

    $editor = Livewire::test(StyleEditor::class, ['projection' => Projection::factory()->create(['user_id' => $user->id])])
        ->call('openEditor', null);

    $editor->set('name', 'Kápolna')->call('create')->assertHasErrors(['name' => 'unique']);
    $editor->set('name', 'Templom')->call('create')->assertHasNoErrors();
});

it('refuses a shrink allowance outside its bounds', function () {
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(StyleEditor::class, ['projection' => Projection::factory()->create(['user_id' => $user->id])])
        ->call('openEditor', null)
        ->set('name', 'Templom')
        ->set('minPercent', 40)
        ->call('create')
        ->assertHasErrors(['minPercent']);
});

it('will not open, change or delete somebody else’s style', function () {
    $user = User::factory()->create();
    $theirs = ProjectionStyle::factory()->create();

    actingAs($user);

    Livewire::test(StyleEditor::class, ['projection' => Projection::factory()->create(['user_id' => $user->id])])
        ->call('openEditor', $theirs->id)
        ->assertForbidden();
});

it('saves one knob of one format at a time, kept to what a style may hold', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    $editor = Livewire::test(StyleEditor::class, ['projection' => Projection::factory()->withStyle($style)->create()])
        ->call('openEditor', $style->id)
        ->call('saveSetting', 'abc', 'abcLyricSize', 30)
        ->call('saveSetting', 'abc', 'abcLyricFont', 'Inter')
        ->call('saveSetting', 'chordpro', 'chordproFontFamily', 'Merriweather')
        ->call('saveSetting', 'abc', 'abcTranspose', 3)
        ->call('saveSetting', 'abc', 'abcLyricSize', 999);

    expect($style->fresh()->settings)->toEqual([
        'chordpro' => ['chordproFontFamily' => "'Merriweather'"],
        'abc' => ['abcLyricFont' => 'Inter', 'abcLyricSize' => 120],
    ]);

    $editor->call('saveSetting', 'abc', 'abcLyricSize', null);

    expect($style->fresh()->settings['abc'])->toBe(['abcLyricFont' => 'Inter']);
});

it('puts a deck in a style at the style’s shape, and the style in its geometry', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->fourThree()->create([
        'user_id' => $user->id,
        'settings' => ['abc' => ['abcLyricSize' => 30]],
        'text_theme' => ProjectionTextTheme::Light,
        'text_size_scale' => 1.3,
        'min_scale' => 0.8,
    ]);
    $projection = Projection::factory()->create(['user_id' => $user->id, 'text_size_scale' => 0.9]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->set('styleId', $style->id)
        ->assertHasNoErrors()
        ->assertSet('ratio', '4/3');

    $geometry = $projection->fresh()->geometry();

    expect($geometry['ratio'])->toBe('4/3')
        ->and($geometry['textTheme'])->toBe('light')
        ->and($geometry['textSizeScale'])->toBe(1.3)
        ->and($geometry['style'])->toBe(['id' => $style->id, 'minScale' => 0.8, 'settings' => ['abc' => ['abcLyricSize' => 30]]])
        ->and(app(ProjectionRenderPayload::class)->for($projection->fresh(), $user)['geometry']['style']['id'])->toBe($style->id);
});

it('gives a deck its own words back when the style is taken off it', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->create(['user_id' => $user->id, 'text_size_scale' => 1.3]);
    $projection = Projection::factory()->withStyle($style)->create(['text_size_scale' => 0.9]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->set('styleId', null)
        ->assertHasNoErrors();

    $geometry = $projection->fresh()->geometry();

    expect($geometry['style'])->toBeNull()
        ->and($geometry['textSizeScale'])->toBe(0.9);
});

it('will not put a deck in somebody else’s style', function () {
    $user = User::factory()->create();
    $theirs = ProjectionStyle::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->set('styleId', $theirs->id)
        ->assertHasErrors(['styleId']);

    expect($projection->fresh()->projection_style_id)->toBeNull();
});

it('moves every deck in a style when the style is edited, and only those', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->create(['user_id' => $user->id]);
    $inIt = Projection::factory()->withStyle($style)->create();
    $notInIt = Projection::factory()->create(['user_id' => $user->id]);

    $before = $inIt->revision();
    $untouched = $notInIt->revision();

    expect(Cache::has(Projection::revisionKey($inIt->id)))->toBeTrue();

    $this->travel(1)->seconds();
    $style->update(['settings' => ['chordpro' => ['chordproFontSize' => 60]]]);

    expect(Cache::has(Projection::revisionKey($inIt->id)))->toBeFalse()
        ->and($inIt->fresh()->revision())->toBeGreaterThan($before)
        ->and($notInIt->fresh()->readRevision())->toBe($untouched);
});

it('leaves the decks alone when a style is only renamed', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->create(['user_id' => $user->id]);
    $projection = Projection::factory()->withStyle($style)->create();

    $before = $projection->readRevision();

    $this->travel(1)->seconds();
    $style->update(['name' => 'Új név']);

    expect($projection->fresh()->readRevision())->toBe($before)
        ->and(array_key_exists('name', $projection->fresh()->geometry()['style']))->toBeFalse();
});

it('sends the decks of a deleted style back to the defaults, and tells their screens', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->create(['user_id' => $user->id]);
    $projection = Projection::factory()->withStyle($style)->create();

    $before = $projection->readRevision();

    actingAs($user);
    $this->travel(1)->seconds();

    Livewire::test(StyleEditor::class, ['projection' => $projection])
        ->call('openEditor', $style->id)
        ->call('delete')
        ->assertDispatched('projection-style-deleted', styleId: $style->id);

    expect(ProjectionStyle::query()->count())->toBe(0)
        ->and($projection->fresh()->projection_style_id)->toBeNull()
        ->and($projection->fresh()->readRevision())->toBeGreaterThan($before);
});

it('copies a style under a free name', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->fourThree()->create(['user_id' => $user->id, 'name' => 'Templom', 'settings' => ['abc' => ['abcLyricSize' => 30]]]);

    $copy = $style->duplicate();
    $again = $style->duplicate();

    expect($copy->name)->not->toBe($style->name)
        ->and($again->name)->not->toBe($copy->name)
        ->and($copy->ratio)->toBe(ProjectionRatio::FourThree)
        ->and($copy->settings)->toBe(['abc' => ['abcLyricSize' => 30]]);
});

it('keeps the style when a deck is copied', function () {
    $style = ProjectionStyle::factory()->create();
    $projection = Projection::factory()->withStyle($style)->create();

    expect($projection->duplicate()->projection_style_id)->toBe($style->id);
});

it('starts a new deck in the style the cantor’s last styled deck was in', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->fourThree()->create(['user_id' => $user->id]);
    Projection::factory()->withStyle($style)->create();

    actingAs($user);

    post(route('projections.store'))->assertRedirect();

    $created = Projection::query()->where('user_id', $user->id)->latest('id')->first();

    expect($created->projection_style_id)->toBe($style->id)
        ->and($created->ratio)->toBe(ProjectionRatio::FourThree);
});

it('has every score follow the style, or keep its own layout, in one press', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $abc = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->create(['format' => ScoreFormat::Abc])->id,
        'settings_override' => ['16/9' => ['abcLyricSize' => 20], '4/3' => ['abcLyricSize' => 10]],
    ]);
    $words = ProjectionSlide::factory()->text()->create(['projection_id' => $projection->id, 'sequence' => 1]);

    actingAs($user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('followStyleEverywhere', true);

    expect($abc->fresh()->settings_override)->toEqual([
        '16/9' => ['abcLyricSize' => 20, 'fromStyle' => true],
        '4/3' => ['abcLyricSize' => 10],
    ])->and($words->fresh()->settings_override)->toBeNull();

    $editor->call('followStyleEverywhere', false);

    expect($abc->fresh()->overrideFor('16/9'))->toEqual(['abcLyricSize' => 20]);
});

it('keeps a slide’s choice to follow the style through its own saves', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $slide = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->create(['user_id' => $user->id, 'format' => ScoreFormat::ChordPro, 'content' => '[C]Egy'])->id,
    ]);

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->call('saveOverride', $slide->id, ['fromStyle' => true, 'chordproFontSize' => 50]);

    expect($slide->fresh()->overrideFor('16/9'))->toEqual(['chordproFontSize' => 50, 'fromStyle' => true]);

    $payload = app(ProjectionRenderPayload::class)->for($projection->fresh(), $user);

    expect($payload['entries'][0]['override'])->toMatchArray(['fromStyle' => true]);
});

it('names the knobs a style holds per format, leaving out what belongs to a piece', function () {
    $keys = ProjectionSettingFields::styleKeys();

    expect(array_keys($keys))->toBe(['chordpro', 'abc', 'gabc', 'aretino'])
        ->and($keys['abc'])->toContain('abcLyricFont', 'abcLyricSize', 'abcPageScale')
        ->and($keys['abc'])->not->toContain('abcTranspose')
        ->and($keys['chordpro'])->not->toContain('chordproTranspose', 'chordproHideChords', 'chordproGermanNotation');
});

it('offers the cantor’s styles on the deck, and every format’s knobs in the editor', function () {
    $user = User::factory()->create();
    $style = ProjectionStyle::factory()->create(['user_id' => $user->id, 'name' => 'Kápolna']);
    $projection = Projection::factory()->withStyle($style)->create();

    actingAs($user);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->assertSee('Kápolna · 16:9')
        ->assertSeeHtml('data-projection-style');

    Livewire::test(StyleEditor::class, ['projection' => $projection])
        ->call('openEditor', $style->id)
        ->assertSeeHtml('data-style-field="abc.abcLyricFont"')
        ->assertSeeHtml('data-style-field="chordpro.chordproFontSize"')
        ->assertSeeHtml('data-style-field="aretino.aretinoStaffSize"')
        ->assertDontSeeHtml('data-style-field="abc.abcTranspose"');
});
