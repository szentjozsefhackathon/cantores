<?php

use App\Livewire\Booklet\EntryRow;
use App\Livewire\Pages\BookletEditor;
use App\Models\Booklet;
use App\Models\BookletMusic;
use App\Models\BookletScore;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use App\Models\User;
use App\Services\BookletRenderPayload;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * The booklet twin of a deck's own music: the two editors are chosen from in the
 * same way, so a music the plan does not have is added, filled and removed the
 * same way in both.
 */

/**
 * A booklet with one slot holding one planned music already printed, and a music
 * nobody planned.
 *
 * @return object{user: User, booklet: Booklet, slot: MusicPlanSlotPlan, plannedRow: BookletScore, birthday: Music, birthdayScore: Score}
 */
function bookletWithRoomForASong(): object
{
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $booklet = Booklet::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);

    $slot = MusicPlanSlotPlan::factory()->create([
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => MusicPlanSlot::factory()->create(['name' => 'Communion'])->id,
        'sequence' => 1,
    ]);

    $planned = Music::factory()->create(['user_id' => $user->id, 'title' => 'Ave verum']);
    $assignment = MusicPlanSlotAssignment::factory()->create(['music_plan_slot_plan_id' => $slot->id, 'music_id' => $planned->id]);

    $birthday = Music::factory()->create(['user_id' => $user->id, 'title' => 'Boldog születésnapot']);

    return (object) [
        'user' => $user,
        'booklet' => $booklet,
        'slot' => $slot,
        'plannedRow' => BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $planned->id])->id,
            'music_plan_slot_assignment_id' => $assignment->id,
            'music_plan_slot_plan_id' => $slot->id,
            'sequence' => 0,
        ]),
        'birthday' => $birthday,
        'birthdayScore' => Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $birthday->id]),
    ];
}

it('adds a music to a booklet without touching the plan, and prints its score under it', function () {
    $booklet = bookletWithRoomForASong();
    $assignments = MusicPlanSlotAssignment::query()->count();

    actingAs($booklet->user);

    $editor = Livewire::test(BookletEditor::class, ['booklet' => $booklet->booklet])
        ->call('startAddingMusic', $booklet->slot->id)
        ->dispatch('music-selected-booklet', musicId: $booklet->birthday->id);

    $added = $booklet->booklet->addedMusics()->sole();

    expect(MusicPlanSlotAssignment::query()->count())->toBe($assignments)
        ->and($added->music_plan_slot_plan_id)->toBe($booklet->slot->id)
        ->and($added->sequence)->toBe(1);

    $editor->call('toggleScore', $booklet->birthdayScore->id, null, null, $added->id);

    $row = $booklet->booklet->entries()->where('score_id', $booklet->birthdayScore->id)->sole();

    expect($row->added_music_id)->toBe($added->id)
        ->and($row->music_plan_slot_plan_id)->toBe($booklet->slot->id)
        ->and($booklet->booklet->entries()->pluck('id')->all())->toBe([$booklet->plannedRow->id, $row->id]);

    $payloads = app(BookletRenderPayload::class);
    $headings = $payloads->headingsFor($payloads->entriesOf($booklet->booklet), $booklet->user);

    expect($headings[$booklet->plannedRow->id]['music'])->toBe('Ave verum')
        ->and($headings[$row->id]['music'])->toBe('Boldog születésnapot');
});

it('names a score under its own added music only in the group heading, not again in the row', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $music = Music::factory()->create(['user_id' => $user->id, 'title' => 'Boldog születésnapot']);
    $added = BookletMusic::factory()->create(['booklet_id' => $booklet->id, 'music_id' => $music->id]);
    $entry = BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id])->id,
        'added_music_id' => $added->id,
    ]);

    actingAs($user);

    expect(Livewire::test(EntryRow::class, ['entry' => $entry])->html())
        ->not->toContain($music->title);
});

it('moves and removes a booklet\'s own music', function () {
    $booklet = bookletWithRoomForASong();
    $added = BookletMusic::factory()->create(['booklet_id' => $booklet->booklet->id, 'music_id' => $booklet->birthday->id, 'sequence' => 1]);
    $row = BookletScore::factory()->create(['booklet_id' => $booklet->booklet->id, 'score_id' => $booklet->birthdayScore->id, 'added_music_id' => $added->id, 'sequence' => 1]);

    actingAs($booklet->user);

    $editor = Livewire::test(BookletEditor::class, ['booklet' => $booklet->booklet])
        ->call('moveAddedMusic', $added->id, -1);

    expect($booklet->booklet->entries()->pluck('id')->all())->toBe([$row->id, $booklet->plannedRow->id]);

    $editor->call('removeAddedMusic', $added->id);

    expect(BookletMusic::query()->find($added->id))->toBeNull()
        ->and(BookletScore::query()->find($row->id))->toBeNull();
});

it('writes words under a booklet\'s own music', function () {
    $booklet = bookletWithRoomForASong();
    $added = BookletMusic::factory()->create(['booklet_id' => $booklet->booklet->id, 'music_id' => $booklet->birthday->id, 'sequence' => 1]);

    actingAs($booklet->user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet->booklet])
        ->call('addText', null, null, null, $added->id);

    $text = $booklet->booklet->entries()->whereNull('score_id')->sole();

    expect($text->added_music_id)->toBe($added->id)
        ->and($text->text)->toBe('');
});

it('copies a booklet\'s own musics and remaps its rows', function () {
    $booklet = bookletWithRoomForASong();
    $added = BookletMusic::factory()->create(['booklet_id' => $booklet->booklet->id, 'music_id' => $booklet->birthday->id]);
    BookletScore::factory()->create(['booklet_id' => $booklet->booklet->id, 'score_id' => $booklet->birthdayScore->id, 'added_music_id' => $added->id, 'sequence' => 1]);

    $copy = $booklet->booklet->duplicate();

    expect($copy->entries()->where('score_id', $booklet->birthdayScore->id)->sole()->added_music_id)
        ->toBe($copy->addedMusics()->sole()->id);
});

// A music sung twice in one service is two places in the pane, and each of them
// has to be able to take the same engraving. Weighed against the whole booklet —
// as it once was — the second occurrence found its only score already taken by
// the first and offered nothing at all, leaving a music that visibly has a score
// saying it has none.
it('offers a music\'s score again where the document holds that music twice', function () {
    $booklet = bookletWithRoomForASong();

    $first = BookletMusic::factory()->create(['booklet_id' => $booklet->booklet->id, 'music_id' => $booklet->birthday->id, 'sequence' => 1]);
    $second = BookletMusic::factory()->create(['booklet_id' => $booklet->booklet->id, 'music_id' => $booklet->birthday->id, 'sequence' => 2]);

    BookletScore::factory()->create([
        'booklet_id' => $booklet->booklet->id,
        'score_id' => $booklet->birthdayScore->id,
        'added_music_id' => $first->id,
        'sequence' => 1,
    ]);

    actingAs($booklet->user);

    $outline = Livewire::test(BookletEditor::class, ['booklet' => $booklet->booklet])->instance()->outline;

    expect(offersUnder($outline, 'added:'.$first->id))->toBe([])
        ->and(collect(offersUnder($outline, 'added:'.$second->id))->pluck('score.id')->all())
        ->toBe([$booklet->birthdayScore->id]);
});

// The look at a score the booklet has not taken. It is nowhere in the payload
// the browser holds — that is only the rows — so this one preview is built by
// the server, and built under the same entitlement a row's is.
it('previews a score offered under a music, without putting it in the booklet', function () {
    $booklet = bookletWithRoomForASong();
    BookletMusic::factory()->create(['booklet_id' => $booklet->booklet->id, 'music_id' => $booklet->birthday->id]);

    actingAs($booklet->user);

    $editor = Livewire::test(BookletEditor::class, ['booklet' => $booklet->booklet]);

    $editor->assertDontSeeHtml('data-offer-preview-modal')
        ->call('previewScore', $booklet->birthdayScore->id)
        ->assertSeeHtml('data-offer-preview-modal')
        ->assertSeeHtml('data-score-preview-sheet');

    expect($editor->instance()->scorePreview)
        ->toMatchArray([
            'kind' => 'score',
            'scoreId' => $booklet->birthdayScore->id,
            'format' => $booklet->birthdayScore->format->value,
            'sections' => null,
            'override' => [],
        ]);

    expect($booklet->booklet->entries()->where('score_id', $booklet->birthdayScore->id)->exists())->toBeFalse();

    // And pressing the same eye again puts it away.
    $editor->call('previewScore', $booklet->birthdayScore->id)
        ->assertDontSeeHtml('data-offer-preview-modal');
});

it('refuses to preview a score the viewer may not read', function () {
    $booklet = bookletWithRoomForASong();
    $theirs = Score::factory()->abc()->create(['user_id' => User::factory()->create()->id]);

    actingAs($booklet->user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet->booklet])
        ->call('previewScore', $theirs->id)
        ->assertDontSeeHtml('data-offer-preview-modal');
});
