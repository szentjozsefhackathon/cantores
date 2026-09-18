<?php

use App\Livewire\Booklet\EntryRow;
use App\Livewire\Projection\SlideRow;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * See plans/score-sections.md. The chip editor is offered only where the
 * score actually has parts to choose from, and the "score changed since"
 * marker is a plain hint rather than anything tracked.
 */
it('renders the booklet rows section chip editor without error', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n%section Refrén\nc d|\n",
    ]);
    $entry = BookletScore::factory()->withSections([2, 1, 5])->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    $html = Livewire::test(EntryRow::class, ['entry' => $entry])->html();

    expect($html)->toContain('data-entry-sections')
        ->and($html)->toContain('Refrén');
});

it('renders the projection rows section chip editor without error', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n%section Refrén\nc d|\n",
    ]);
    $entry = ProjectionSlide::factory()->withSections([2, 1])->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    $html = Livewire::test(SlideRow::class, ['entry' => $entry])->html();

    expect($html)->toContain('data-entry-sections')
        ->and($html)->toContain('Refrén');
});

/*
 * The booklet keeps EntryRow deliberately untouched when it redraws itself
 * (see EntryRow's own docblock), so a section written by the booklet has to
 * reach the row through this scoped event rather than through the parent's
 * own re-render.
 */
it('refreshes its stale sections when told the booklet changed them', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'content' => "X:1\nK:G\n%section 1\nA B|\n%section Refrén\nc d|\n",
    ]);
    $entry = BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    $row = Livewire::test(EntryRow::class, ['entry' => $entry]);

    expect($row->instance()->entry->sections)->toBeNull();

    // The booklet writes the change directly, as it would through
    // BookletEditor::addSection() — the row never sees that request.
    $entry->update(['sections' => [2]]);

    $row->dispatch("booklet-entry-sections-changed.{$entry->id}");

    expect($row->instance()->entry->sections)->toBe([2]);
});

it('hides the chip editor for a score with no markers', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
    ]);

    actingAs($user);

    expect(Livewire::test(EntryRow::class, ['entry' => $entry])->html())
        ->not->toContain('data-entry-sections');
});

it('marks a row whose score has changed since, and clears the mark once the row is saved', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    $entry = BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $score->touch();

    actingAs($user);

    expect(Livewire::test(EntryRow::class, ['entry' => $entry])->html())
        ->toContain('data-entry-score-changed');

    $entry->update(['show_slot' => ! $entry->show_slot]);

    expect(Livewire::test(EntryRow::class, ['entry' => $entry->fresh()])->html())
        ->not->toContain('data-entry-score-changed');
});
