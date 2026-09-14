<?php

use App\Enums\ProjectionRatio;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\User;

/*
 * What a projection is, before anything draws one.
 *
 * The deck is deliberately thin: a shape and an ordered list of scores. Almost
 * everything a booklet has to state — the page, the margins, the one size every
 * score is unified to — has no counterpart here, because the author of each
 * score has already answered it for this very ratio in the score editor. These
 * are the few things the row in the database does have to get right.
 */

it('opens on a widescreen, which is what a projector is', function () {
    $projection = Projection::factory()->create();

    expect($projection->ratio)->toBe(ProjectionRatio::SixteenNine)
        ->and($projection->ratio->label())->toBe('16:9')
        ->and($projection->geometry())->toMatchArray([
            'ratio' => '16/9',
            'aspectRatio' => '16/9',
        ]);
});

// The stored value is the key a score's own settings bucket is written under,
// so it has to stay the slash spelling however it is shown to a reader.
it('stores the ratio the way a scores settings are keyed by it', function () {
    foreach ([['16/9', '16:9'], ['4/3', '4:3'], ['1/1', '1:1']] as [$stored, $shown]) {
        $ratio = ProjectionRatio::from($stored);

        expect($ratio->value)->toBe($stored)
            ->and($ratio->label())->toBe($shown)
            ->and($ratio->css())->toBe($stored);
    }

    expect(ProjectionRatio::options())->toBe([
        '16/9' => '16:9',
        '4/3' => '4:3',
        '1/1' => '1:1',
    ]);
});

it('knows how tall a screen of each shape is', function () {
    expect(ProjectionRatio::SixteenNine->heightFor(1920))->toBe(1080.0)
        ->and(ProjectionRatio::FourThree->heightFor(1440))->toBe(1080.0)
        ->and(ProjectionRatio::OneOne->heightFor(1080))->toBe(1080.0);
});

it('reads its slides in the order they are projected', function () {
    $projection = Projection::factory()->create();

    foreach ([2, 0, 1] as $sequence) {
        ProjectionSlide::factory()->create([
            'projection_id' => $projection->id,
            'sequence' => $sequence,
        ]);
    }

    expect($projection->entries()->pluck('sequence')->all())->toBe([0, 1, 2]);
});

// A row may hold words instead of music — a rubric on a screen of its own.
it('lets a row carry words rather than a score', function () {
    $projection = Projection::factory()->create();

    $text = ProjectionSlide::factory()->text('Álljunk fel.')->create([
        'projection_id' => $projection->id,
    ]);

    expect($text->isText())->toBeTrue()
        ->and($text->text)->toBe('Álljunk fel.')
        ->and($text->score_id)->toBeNull();

    $score = ProjectionSlide::factory()->create(['projection_id' => $projection->id]);

    expect($score->isText())->toBeFalse();
});

/*
 * A congregation is being sung to rather than read to. A booklet announces the
 * moment in the service and the book a hymn can be looked up in; a screen that
 * did the same would spend half of itself on words nobody came to read.
 */
it('names much less on a screen than a booklet does on a page', function () {
    $slide = ProjectionSlide::factory()->create();

    expect($slide->show_music_title)->toBeFalse()
        ->and($slide->show_slot)->toBeFalse()
        ->and($slide->show_collections)->toBeFalse()
        ->and($slide->show_variation)->toBeFalse();
});

it('keeps a hand-made adjustment on the slide rather than on the score', function () {
    $slide = ProjectionSlide::factory()->create([
        'settings_override' => ['abcLyricSize' => 40],
    ]);

    expect($slide->fresh()->settings_override)->toBe(['abcLyricSize' => 40]);
});

// A deck already projected at a service outlives the plan it was built from.
it('survives the plan it was built from', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $projection = Projection::factory()->forPlan($plan)->create();

    $plan->forceDelete();

    expect($projection->fresh())->not->toBeNull()
        ->and($projection->fresh()->music_plan_id)->toBeNull();
});

it('takes its slides with it when it goes', function () {
    $projection = Projection::factory()->create();
    ProjectionSlide::factory()->count(2)->create(['projection_id' => $projection->id]);

    $projection->forceDelete();

    expect(ProjectionSlide::query()->where('projection_id', $projection->id)->count())->toBe(0);
});

// A plan may have both, and more than one of each.
it('hangs off the plan beside the booklets made from it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    Projection::factory()->forPlan($plan)->create();
    Projection::factory()->forPlan($plan)->fourThree()->create();

    expect($plan->projections()->count())->toBe(2)
        ->and($plan->projections()->pluck('ratio')->all())
        ->toBe([ProjectionRatio::SixteenNine, ProjectionRatio::FourThree]);
});

it('belongs to one person and is listed for nobody else', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    Projection::factory()->count(2)->create(['user_id' => $mine->id]);
    Projection::factory()->create(['user_id' => $theirs->id]);

    expect(Projection::query()->mine($mine)->count())->toBe(2)
        ->and(Projection::query()->mine($theirs)->count())->toBe(1);
});

/*
 * Named for the celebration and its date, which is how anyone looking for it
 * next year will think of it — the same way a booklet is named.
 */
it('is named after the celebration it was built for', function () {
    expect(Projection::titleFor(null))->toBe(__('Projection'));

    $celebration = Celebration::factory()->create([
        'name' => 'Advent 1. vasárnapja',
        'actual_date' => '2026-11-29',
    ]);
    $plan = MusicPlan::factory()->create(['celebration_id' => $celebration->id]);

    expect(Projection::titleFor($plan->fresh()))->toContain('Advent 1. vasárnapja');
});

it('reaches its scores through the slides', function () {
    $projection = Projection::factory()->create();
    $score = Score::factory()->create();

    ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    expect($projection->scores()->pluck('scores.id')->all())->toBe([$score->id]);
});
