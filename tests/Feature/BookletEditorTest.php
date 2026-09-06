<?php

use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\Booklets;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\User;
use App\Support\BookletSettingFields;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function bookletFor(User $user, ?MusicPlan $plan = null): Booklet
{
    return Booklet::factory()->create([
        'user_id' => $user->id,
        'music_plan_id' => $plan?->getKey(),
    ]);
}

it('creates a booklet from a music plan and names it after the celebration', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    actingAs($user);

    post(route('booklets.store'), ['music_plan_id' => $plan->id])
        ->assertRedirect();

    $booklet = Booklet::query()->where('user_id', $user->id)->firstOrFail();

    expect($booklet->music_plan_id)->toBe($plan->id)
        ->and($booklet->page_size->value)->toBe('a5')
        ->and($booklet->entries()->count())->toBe(0);
});

it('refuses to start a booklet from someone elses private plan', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $owner->id, 'is_private' => true]);

    actingAs($stranger);

    post(route('booklets.store'), ['music_plan_id' => $plan->id])->assertForbidden();
});

it('adds a score and gives it the next place in the order', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $first = Score::factory()->abc()->create(['user_id' => $user->id]);
    $second = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $first->id)
        ->call('toggleScore', $second->id);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$first->id, $second->id]);
});

it('removes a score when it is toggled again', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $score->id)
        ->call('toggleScore', $score->id);

    expect($booklet->entries()->count())->toBe(0);
});

it('will not pull in a score the viewer cannot read', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = bookletFor($user);
    $theirs = Score::factory()->abc()->create(['user_id' => $stranger->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $theirs->id);

    expect($booklet->entries()->count())->toBe(0);
});

/**
 * @return array{0: \App\Models\Booklet, 1: \Illuminate\Support\Collection<int, BookletScore>}
 */
function bookletWithEntries(User $user, int $count): array
{
    $booklet = bookletFor($user);

    $entries = Score::factory()->abc()->count($count)->create(['user_id' => $user->id])
        ->values()
        ->map(fn (Score $score, int $index): BookletScore => BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => $index,
        ]));

    return [$booklet, $entries];
}

it('reorders scores and renumbers the whole list', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 3);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $entries[2]->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[2]->id, $entries[1]->id]);
});

it('leaves the order alone when a score is already at the end', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 2);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $entries[1]->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[1]->id]);
});

// Sequences drift apart as entries come and go; a move must close the gaps
// rather than move one entry into a number another entry already holds.
it('renumbers sequences that had drifted out of step', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 3);

    $entries[1]->update(['sequence' => 5]);
    $entries[2]->update(['sequence' => 9]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $entries[0]->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('sequence')->all())
        ->toBe([0, 1, 2])
        ->and($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[1]->id, $entries[0]->id, $entries[2]->id]);
});

it('leaves the order alone when the entry belongs to another booklet', function () {
    $user = User::factory()->create();
    [$booklet, $entries] = bookletWithEntries($user, 3);
    $elsewhere = BookletScore::factory()->create(['booklet_id' => bookletFor($user)->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $elsewhere->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('id')->all())
        ->toBe([$entries[0]->id, $entries[1]->id, $entries[2]->id]);
});

// A Blade directive inside a component tag — `@disabled(...)` — stops Blade from
// compiling that tag at all. The browser is then handed a literal <flux:button>
// it never closes, which both hides the control and swallows everything after it
// into a phantom element, so the next Livewire update has nothing to morph onto.
it('compiles every component tag on the page', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem', 'Uram, irgalmazz']);

    actingAs($user);

    $html = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id)
        ->call('addText')
        ->html();

    expect($html)->not->toContain('<flux:');
});

it('saves geometry changes as they are made', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->set('pageSize', 'a4')
        ->set('lyricSizePt', 12.5)
        ->assertHasNoErrors();

    $booklet->refresh();

    expect($booklet->page_size->value)->toBe('a4')
        ->and($booklet->lyric_size_pt)->toBe(12.5)
        ->and($booklet->contentMm()['width'])->toBe(210.0 - 24);
});

// The whole point of holding overrides on the pivot: a booklet adjusts how a
// score is printed here, and changes nothing anywhere else.
it('keeps an override to one booklet and never writes it back to the score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'settings' => ['abc' => ['paper' => ['abcPageWidth' => 1700]]]]);
    $one = bookletFor($user);
    $two = bookletFor($user);

    $entry = BookletScore::factory()->create(['booklet_id' => $one->id, 'score_id' => $score->id]);
    BookletScore::factory()->create(['booklet_id' => $two->id, 'score_id' => $score->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $one])
        ->call('saveOverride', $entry->id, ['abcPageWidth' => 700]);

    // Whole numbers come back from JSON as ints, so compare by value.
    expect($one->entries()->first()->settings_override)->toEqual(['abcPageWidth' => 700])
        ->and($two->entries()->first()->settings_override)->toBeNull()
        ->and($score->fresh()->settings)->toBe(['abc' => ['paper' => ['abcPageWidth' => 1700]]]);
});

it('clamps an out-of-range override and drops keys the format does not have', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = BookletScore::factory()->create(['booklet_id' => $booklet->id, 'score_id' => $score->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $entry->id, [
            'abcPageWidth' => 999999,
            'aretinoStaffWidth' => 120,
            'nonsense' => 'x',
        ]);

    expect($booklet->entries()->first()->settings_override)->toEqual(['abcPageWidth' => 8000]);
});

it('forgets an override when it is reset', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
        'settings_override' => ['abcPageWidth' => 700],
    ]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('resetOverride', $entry->id);

    expect($booklet->entries()->first()->settings_override)->toBeNull();
});

it('only accepts a font the exporter can embed', function () {
    expect(BookletSettingFields::sanitize('abc', ['abcLyricFont' => 'Lora']))
        ->toBe(['abcLyricFont' => "'Lora'"])
        ->and(BookletSettingFields::sanitize('abc', ['abcLyricFont' => 'Comic Sans MS']))
        ->toBe([]);
});

it('hands the browser everything it needs to draw a score', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id, 'title' => 'Kyrie']);
    BookletScore::factory()->create(['booklet_id' => $booklet->id, 'score_id' => $score->id]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->get('renderPayload');

    expect($payload)->toHaveCount(1)
        ->and($payload[0]['kind'])->toBe('score')
        ->and($payload[0]['format'])->toBe('abc')
        ->and($payload[0]['content'])->toContain('X:1')
        ->and($payload[0]['credit'])->toBeNull();
});

/**
 * A slot occurrence with the given musics, and one abc score for each.
 *
 * @param  list<string>  $musicTitles
 * @return array{0: \App\Models\MusicPlanSlotPlan, 1: \Illuminate\Support\Collection<int, MusicPlanSlotAssignment>, 2: \Illuminate\Support\Collection<int, Score>}
 */
function slotWithMusics(MusicPlan $plan, string $slotName, array $musicTitles): array
{
    $slot = MusicPlanSlot::factory()->create(['name' => $slotName]);
    $slotPlan = MusicPlanSlotPlan::factory()->create([
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => $slot->id,
    ]);

    $assignments = collect();
    $scores = collect();

    foreach (array_values($musicTitles) as $index => $title) {
        $music = Music::factory()->create(['user_id' => $plan->user_id, 'title' => $title]);
        $assignments->push(MusicPlanSlotAssignment::factory()->create([
            'music_plan_slot_plan_id' => $slotPlan->id,
            'music_id' => $music->id,
            'music_sequence' => $index,
        ]));
        $scores->push(Score::factory()->abc()->create([
            'user_id' => $plan->user_id,
            'music_id' => $music->id,
            'title' => $title,
            'variation_name' => $title.' – orgonakíséret',
        ]));
    }

    return [$slotPlan, $assignments, $scores];
}

it('names a lone music beside its slot and says nothing of the score', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])->get('renderPayload');

    expect($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem')
        ->and($payload[0]['music'])->toBeNull()
        ->and($payload[0]['variation'])->toBeNull();
});

it('announces a shared slot once and names each music under it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Áldozás', ['Ének egy', 'Ének kettő']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id);

    $payload = $component->get('renderPayload');

    expect($payload[0]['slot'])->toBe('Áldozás')
        ->and($payload[0]['music'])->toBe('Ének egy')
        ->and($payload[1]['slot'])->toBeNull()
        ->and($payload[1]['music'])->toBe('Ének kettő');
});

it('prints the variation name only for the score that asked for it', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    $entryId = $booklet->entries()->firstOrFail()->id;

    $component->call('toggleShowVariation', $entryId);

    expect($component->get('renderPayload')[0]['variation'])
        ->toBe('Áldjad, én lelkem – orgonakíséret');
});

// A heading is read by whoever holds the booklet: it must come from that
// booklet's own service, never from someone else's.
it('ignores an assignment that is not in the booklets plan', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $other = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($other, 'Felajánlás', ['Idegen ének']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id);

    expect($booklet->entries()->firstOrFail()->music_plan_slot_assignment_id)->toBeNull();
});

it('adds a paragraph of instructions and keeps its Markdown', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('addText')
        ->set('editingText', "**Álljunk fel.**\n\nA kántor énekli a verseket.");

    $entry = $booklet->entries()->firstOrFail();

    expect($entry->isText())->toBeTrue()
        ->and($entry->text)->toContain('Álljunk fel')
        ->and($component->get('renderPayload')[0])
        ->toMatchArray(['kind' => 'text', 'id' => $entry->id]);
});

it('keeps the heading run across a paragraph of instructions', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Áldozás', ['Ének egy', 'Ének kettő']);

    actingAs($user);

    $component = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('addText')
        ->call('toggleScore', $scores[1]->id, $assignments[1]->id);

    $payload = $component->get('renderPayload');

    expect($payload[1]['kind'])->toBe('text')
        ->and($payload[2]['slot'])->toBeNull()
        ->and($payload[2]['music'])->toBe('Ének kettő');
});

it('does not repeat a lone slot heading after a paragraph of instructions', function () {
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = bookletFor($user, $plan);
    [, $assignments, $scores] = slotWithMusics($plan, 'Kezdőének', ['Áldjad, én lelkem']);

    // A second engraving of the same music, so the slot is still held by one
    // music and its heading is the one that must not come round again.
    $organ = Score::factory()->abc()->create([
        'user_id' => $user->id,
        'music_id' => $scores[0]->music_id,
        'title' => 'Áldjad, én lelkem',
    ]);

    actingAs($user);

    $payload = Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('toggleScore', $scores[0]->id, $assignments[0]->id)
        ->call('addText')
        ->call('toggleScore', $organ->id, $assignments[0]->id)
        ->get('renderPayload');

    expect($payload[0]['slot'])->toBe('Kezdőének – Áldjad, én lelkem')
        ->and($payload[1]['kind'])->toBe('text')
        ->and($payload[2]['slot'])->toBeNull()
        ->and($payload[2]['music'])->toBeNull();
});

it('does not put a score panel on a text entry', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])->call('addText');

    $entry = $booklet->entries()->firstOrFail();

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $entry->id, ['abcPageWidth' => 700]);

    expect($entry->fresh()->settings_override)->toBeNull();
});

it('refuses to open or change someone elses booklet', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = bookletFor($owner);

    actingAs($stranger);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertForbidden();
});

// A booklet is the scores of one service, so the list starts one by choosing
// that service rather than by making an empty booklet and wondering.
it('offers my own plans and starts a booklet from the chosen one', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $mine->id]);
    $notMine = MusicPlan::factory()->create(['user_id' => $theirs->id, 'is_private' => true]);

    actingAs($mine);

    $component = Livewire::test(Booklets::class);

    expect($component->get('selectablePlans')->pluck('id')->all())
        ->toBe([$plan->id]);

    $component->call('createFromPlan', $plan->id)
        ->assertRedirect(route('booklets.edit', ['booklet' => Booklet::query()->firstOrFail()->id]));

    expect(Booklet::query()->firstOrFail()->music_plan_id)->toBe($plan->id);

    $component->call('createFromPlan', $notMine->id)->assertForbidden();
});

it('lists only my own booklets', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    Booklet::factory()->create(['user_id' => $mine->id, 'title' => 'Adventi füzet']);
    Booklet::factory()->create(['user_id' => $theirs->id, 'title' => 'Karácsonyi füzet']);

    actingAs($mine);

    Livewire::test(Booklets::class)
        ->assertSee('Adventi füzet')
        ->assertDontSee('Karácsonyi füzet');
});
