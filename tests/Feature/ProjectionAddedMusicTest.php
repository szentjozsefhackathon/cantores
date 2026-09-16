<?php

use App\Livewire\Pages\ProjectionEditor;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionMusic;
use App\Models\ProjectionSlide;
use App\Models\Score;
use App\Models\User;
use App\Services\PlanOutline;
use App\Services\ProjectionRenderPayload;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * The song asked for a minute before Mass: a music the deck holds and its plan
 * does not. It is added to the deck, stays in the deck, and the plan — the
 * published service — does not hear about it.
 */

/**
 * A plan of three slots — Gloria and Communion each with one music chosen into
 * the deck, Offertory with nothing planned — and a music nobody planned.
 *
 * @return object{user: User, projection: Projection, gloria: MusicPlanSlotPlan, offertory: MusicPlanSlotPlan, communion: MusicPlanSlotPlan, gloriaRow: ProjectionSlide, communionRow: ProjectionSlide, birthday: Music, birthdayScore: Score}
 */
function deckWithRoomForASong(): object
{
    $user = User::factory()->create();
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);
    $projection = Projection::factory()->create(['user_id' => $user->id, 'music_plan_id' => $plan->id]);

    $slotOf = fn (string $name, int $sequence): MusicPlanSlotPlan => MusicPlanSlotPlan::factory()->create([
        'music_plan_id' => $plan->id,
        'music_plan_slot_id' => MusicPlanSlot::factory()->create(['name' => $name])->id,
        'sequence' => $sequence,
    ]);

    $gloria = $slotOf('Gloria', 1);
    $offertory = $slotOf('Offertory', 2);
    $communion = $slotOf('Communion', 3);

    $rowIn = function (MusicPlanSlotPlan $slot, string $title, int $sequence) use ($user, $projection): ProjectionSlide {
        $music = Music::factory()->create(['user_id' => $user->id, 'title' => $title]);
        $assignment = MusicPlanSlotAssignment::factory()->create(['music_plan_slot_plan_id' => $slot->id, 'music_id' => $music->id]);
        $score = Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $music->id]);

        return ProjectionSlide::factory()->create([
            'projection_id' => $projection->id,
            'score_id' => $score->id,
            'music_plan_slot_assignment_id' => $assignment->id,
            'music_plan_slot_plan_id' => $slot->id,
            'sequence' => $sequence,
            'show_slot' => true,
            'show_music_title' => true,
        ]);
    };

    $birthday = Music::factory()->create(['user_id' => $user->id, 'title' => 'Boldog születésnapot']);

    return (object) [
        'user' => $user,
        'projection' => $projection,
        'gloria' => $gloria,
        'offertory' => $offertory,
        'communion' => $communion,
        'gloriaRow' => $rowIn($gloria, 'Glória', 0),
        'communionRow' => $rowIn($communion, 'Ave verum', 1),
        'birthday' => $birthday,
        'birthdayScore' => Score::factory()->abc()->create(['user_id' => $user->id, 'music_id' => $birthday->id, 'title' => 'Születésnap – I']),
    ];
}

/**
 * The deck's top level as keys, which is the order the pane and the wall read.
 *
 * @return list<string>
 */
function topLevelKeys(Projection $projection): array
{
    $outline = app(PlanOutline::class)->for($projection->fresh(), $projection->entries()->get());

    return array_map(
        fn (array $node): string => $node['kind'] === 'entry' ? 'entry:'.$node['entry']->id : $node['key'],
        $outline,
    );
}

/**
 * @param  list<array<string, mixed>>  $nodes
 * @return array<string, mixed>|null
 */
function addedNodeIn(array $nodes, int $addedMusicId): ?array
{
    foreach ($nodes as $node) {
        if ($node['kind'] === 'entry') {
            continue;
        }

        if (($node['local'] ?? false) && $node['id'] === $addedMusicId) {
            return $node;
        }

        $found = addedNodeIn($node['children'], $addedMusicId);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

it('adds a music between slots at the end of the deck without touching the plan', function () {
    $deck = deckWithRoomForASong();
    $assignments = MusicPlanSlotAssignment::query()->count();

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('startAddingMusic')
        ->dispatch('music-selected-projection', musicId: $deck->birthday->id)
        ->assertSet('openedAddedMusicId', fn (?int $id): bool => $id !== null);

    $added = $deck->projection->addedMusics()->sole();

    expect(MusicPlanSlotAssignment::query()->count())->toBe($assignments)
        ->and($added->music_plan_slot_plan_id)->toBeNull()
        ->and($added->sequence)->toBe(2)
        ->and(topLevelKeys($deck->projection))->toBe(['slot:'.$deck->gloria->id, 'slot:'.$deck->offertory->id, 'slot:'.$deck->communion->id, 'added:'.$added->id]);
});

it('offers adding a music to every slot, empty ones included', function () {
    $deck = deckWithRoomForASong();

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->assertSeeHtml('wire:click="startAddingMusic('.$deck->gloria->id.')"')
        ->assertSeeHtml('wire:click="startAddingMusic('.$deck->offertory->id.')"')
        ->assertSeeHtml('wire:click="startAddingMusic('.$deck->communion->id.')"');
});

it('adds a music inside a slot at the end of that slot', function () {
    $deck = deckWithRoomForASong();

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('startAddingMusic', $deck->gloria->id)
        ->call('addMusic', $deck->birthday->id);

    $added = $deck->projection->addedMusics()->sole();
    $outline = app(PlanOutline::class)->for($deck->projection, $deck->projection->entries()->get());
    $gloria = collect($outline)->firstWhere('key', 'slot:'.$deck->gloria->id);

    expect($added->music_plan_slot_plan_id)->toBe($deck->gloria->id)
        ->and($added->sequence)->toBe(1)
        ->and(last($gloria['children'])['key'])->toBe('added:'.$added->id);
});

it('does not add a music the editor cannot see', function () {
    $deck = deckWithRoomForASong();
    $hidden = Music::factory()->private()->create();

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('addMusic', $hidden->id);

    expect($deck->projection->addedMusics()->exists())->toBeFalse();
});

it('keeps an empty added music in its place through reopening and moving other things', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create([
        'projection_id' => $deck->projection->id,
        'music_id' => $deck->birthday->id,
        'sequence' => 1,
    ]);

    actingAs($deck->user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection]);

    expect(topLevelKeys($deck->projection))->toBe(['slot:'.$deck->gloria->id, 'added:'.$added->id, 'slot:'.$deck->offertory->id, 'slot:'.$deck->communion->id]);

    $editor->call('moveSlot', $deck->communion->id, -1);

    expect(topLevelKeys($deck->projection))->toBe(['slot:'.$deck->offertory->id, 'slot:'.$deck->communion->id, 'added:'.$added->id, 'slot:'.$deck->gloria->id])
        ->and($added->fresh()->sequence)->toBe(1);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('toggleScore', $deck->birthdayScore->id, null, null, $added->id);

    $row = $deck->projection->entries()->where('score_id', $deck->birthdayScore->id)->sole();

    expect($row->added_music_id)->toBe($added->id)
        ->and($deck->projection->entries()->pluck('id')->all())->toBe([$deck->communionRow->id, $row->id, $deck->gloriaRow->id]);
});

it('moves an empty added music across whole slots', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create([
        'projection_id' => $deck->projection->id,
        'music_id' => $deck->birthday->id,
        'sequence' => 2,
    ]);

    actingAs($deck->user);

    $editor = Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('moveAddedMusic', $added->id, -1);

    expect($added->fresh()->sequence)->toBe(1);

    $editor->call('moveAddedMusic', $added->id, -1);

    expect($added->fresh()->sequence)->toBe(0)
        ->and(topLevelKeys($deck->projection)[0])->toBe('added:'.$added->id);
});

it('closes up the place of an empty music when a row before it is removed', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create([
        'projection_id' => $deck->projection->id,
        'music_id' => $deck->birthday->id,
        'sequence' => 2,
    ]);

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('removeEntry', $deck->gloriaRow->id);

    expect($added->fresh()->sequence)->toBe(1);
});

it('offers only the scores the service list would show, and toggles in nothing else', function () {
    $deck = deckWithRoomForASong();
    $stranger = User::factory()->create();
    $theirs = Score::factory()->abc()->create(['user_id' => $stranger->id, 'music_id' => $deck->birthday->id]);
    $added = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'sequence' => 2]);

    actingAs($deck->user);

    $outline = app(PlanOutline::class)->for($deck->projection, $deck->projection->entries()->get());
    $offered = collect(addedNodeIn($outline, $added->id)['offers'])->pluck('score.id')->all();

    expect($offered)->toBe([$deck->birthdayScore->id]);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('toggleScore', $theirs->id, null, null, $added->id);

    expect($deck->projection->entries()->where('score_id', $theirs->id)->exists())->toBeFalse();
});

it('does not file a score under another deck\'s music', function () {
    $deck = deckWithRoomForASong();
    $foreign = ProjectionMusic::factory()->create(['music_id' => $deck->birthday->id]);

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('toggleScore', $deck->birthdayScore->id, null, null, $foreign->id);

    expect($deck->projection->entries()->where('score_id', $deck->birthdayScore->id)->sole()->added_music_id)->toBeNull();
});

it('names an added music under its slot, and on its own line beside planned music', function () {
    $deck = deckWithRoomForASong();
    $inOffertory = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'music_plan_slot_plan_id' => $deck->offertory->id]);
    $inCommunion = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'music_plan_slot_plan_id' => $deck->communion->id]);
    $other = Score::factory()->abc()->create(['user_id' => $deck->user->id, 'music_id' => $deck->birthday->id]);

    $flags = ['show_slot' => true, 'show_music_title' => true];
    $alone = ProjectionSlide::factory()->create([...$flags, 'projection_id' => $deck->projection->id, 'score_id' => $deck->birthdayScore->id, 'added_music_id' => $inOffertory->id, 'music_plan_slot_plan_id' => $deck->offertory->id, 'sequence' => 1]);
    $beside = ProjectionSlide::factory()->create([...$flags, 'projection_id' => $deck->projection->id, 'score_id' => $other->id, 'added_music_id' => $inCommunion->id, 'music_plan_slot_plan_id' => $deck->communion->id, 'sequence' => 3]);
    $deck->communionRow->update(['sequence' => 2]);

    actingAs($deck->user);

    $payloads = app(ProjectionRenderPayload::class);
    $headings = $payloads->headingsFor($payloads->entriesOf($deck->projection), $deck->user);

    expect($headings[$alone->id]['slot'])->toBe('Offertory – Boldog születésnapot')
        ->and($headings[$alone->id]['music'])->toBeNull()
        ->and($headings[$deck->communionRow->id]['slot'])->toBe('Communion')
        ->and($headings[$deck->communionRow->id]['music'])->toBe('Ave verum')
        ->and($headings[$beside->id]['slot'])->toBeNull()
        ->and($headings[$beside->id]['music'])->toBe('Boldog születésnapot');
});

it('names an added music between slots by its title, once, and honours the switches', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id]);
    $other = Score::factory()->abc()->create(['user_id' => $deck->user->id, 'music_id' => $deck->birthday->id, 'variation_name' => 'Kánon']);

    $first = ProjectionSlide::factory()->create(['projection_id' => $deck->projection->id, 'score_id' => $deck->birthdayScore->id, 'added_music_id' => $added->id, 'sequence' => 2, 'show_slot' => true, 'show_music_title' => true]);
    $second = ProjectionSlide::factory()->create(['projection_id' => $deck->projection->id, 'score_id' => $other->id, 'added_music_id' => $added->id, 'sequence' => 3, 'show_slot' => true, 'show_variation' => true]);

    actingAs($deck->user);

    $payloads = app(ProjectionRenderPayload::class);
    $headings = $payloads->headingsFor($payloads->entriesOf($deck->projection), $deck->user);

    expect($headings[$first->id])->toMatchArray(['slot' => 'Boldog születésnapot', 'music' => null, 'reference' => null, 'variation' => null])
        ->and($headings[$second->id])->toMatchArray(['slot' => null, 'music' => null, 'variation' => 'Kánon']);

    $first->update(['show_slot' => false]);

    $headings = $payloads->headingsFor($payloads->entriesOf($deck->projection), $deck->user);

    expect($headings[$first->id]['slot'])->toBeNull();
});

it('removes an added music with its rows, and keeps it when its slot leaves the plan', function () {
    $deck = deckWithRoomForASong();
    $removed = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id]);
    $row = ProjectionSlide::factory()->create(['projection_id' => $deck->projection->id, 'score_id' => $deck->birthdayScore->id, 'added_music_id' => $removed->id, 'sequence' => 2]);
    $kept = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'music_plan_slot_plan_id' => $deck->offertory->id]);

    actingAs($deck->user);

    Livewire::test(ProjectionEditor::class, ['projection' => $deck->projection])
        ->call('removeAddedMusic', $removed->id);

    expect(ProjectionMusic::query()->find($removed->id))->toBeNull()
        ->and(ProjectionSlide::query()->find($row->id))->toBeNull();

    $deck->offertory->delete();

    expect($kept->fresh()->music_plan_slot_plan_id)->toBeNull();
});

it('copies added musics with the deck, and points the copied rows at the copies', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'music_plan_slot_plan_id' => $deck->offertory->id, 'sequence' => 1]);
    ProjectionSlide::factory()->create(['projection_id' => $deck->projection->id, 'score_id' => $deck->birthdayScore->id, 'added_music_id' => $added->id, 'sequence' => 1]);

    $copy = $deck->projection->duplicate();
    $copiedMusic = $copy->addedMusics()->sole();

    expect($copiedMusic->id)->not->toBe($added->id)
        ->and($copiedMusic->music_plan_slot_plan_id)->toBe($deck->offertory->id)
        ->and($copiedMusic->sequence)->toBe(1)
        ->and($copy->entries()->where('score_id', $deck->birthdayScore->id)->sole()->added_music_id)->toBe($copiedMusic->id);
});

/*
 * ---------------------------------------------------------------------------
 * The remote.
 * ---------------------------------------------------------------------------
 */

it('searches music for the remote as JSON', function () {
    $deck = deckWithRoomForASong();

    actingAs($deck->user);

    getJson(route('projections.music-search', ['projection' => $deck->projection, 'q' => 'születésnap']))
        ->assertOk()
        ->assertJsonPath('musics.0.id', $deck->birthday->id)
        ->assertJsonPath('musics.0.title', 'Boldog születésnapot');
});

it('adds a music from the remote at the end of the deck, or of a slot, and the wall hears of it at once', function () {
    $deck = deckWithRoomForASong();

    actingAs($deck->user);

    $before = $deck->projection->revision();
    $this->travel(1)->seconds();

    $response = postJson(route('projections.added-musics.store', ['projection' => $deck->projection]), ['music_id' => $deck->birthday->id])
        ->assertOk();

    $atEnd = $deck->projection->addedMusics()->sole();

    expect($atEnd->sequence)->toBe(2)
        ->and($atEnd->music_plan_slot_plan_id)->toBeNull()
        ->and($response->json('revision'))->not->toBe($before)
        ->and($deck->projection->fresh()->revision())->toBe($response->json('revision'))
        ->and(last($response->json('outline')))->toMatchArray(['kind' => 'music', 'local' => true, 'addedMusicId' => $atEnd->id]);

    postJson(route('projections.added-musics.store', ['projection' => $deck->projection]), ['music_id' => $deck->birthday->id, 'slot_plan_id' => $deck->gloria->id])
        ->assertOk();

    expect($deck->projection->addedMusics()->where('music_plan_slot_plan_id', $deck->gloria->id)->sole()->sequence)->toBe(1);
});

it('refuses a slot from another plan', function () {
    $deck = deckWithRoomForASong();
    $elsewhere = MusicPlanSlotPlan::factory()->create();

    actingAs($deck->user);

    postJson(route('projections.added-musics.store', ['projection' => $deck->projection]), ['music_id' => $deck->birthday->id, 'slot_plan_id' => $elsewhere->id])
        ->assertUnprocessable();
});

it('toggles a score of an added music from the remote while the deck is live, and removes the music', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'sequence' => 2]);
    Presentation::factory()->create(['projection_id' => $deck->projection->id, 'user_id' => $deck->user->id, 'entry_id' => $deck->gloriaRow->id]);

    actingAs($deck->user);

    postJson(route('projections.score-toggle', ['projection' => $deck->projection]), [
        'scoreId' => $deck->birthdayScore->id,
        'addedMusicId' => $added->id,
    ])->assertOk()->assertJsonCount(3, 'entries');

    expect($deck->projection->entries()->where('added_music_id', $added->id)->count())->toBe(1);

    deleteJson(route('projections.added-musics.destroy', ['projection' => $deck->projection, 'addedMusic' => $added]))
        ->assertOk()
        ->assertJsonCount(2, 'entries');
});

it('does not let the remote remove a music of another deck', function () {
    $deck = deckWithRoomForASong();
    $foreign = ProjectionMusic::factory()->create(['music_id' => $deck->birthday->id]);

    actingAs($deck->user);

    deleteJson(route('projections.added-musics.destroy', ['projection' => $deck->projection, 'addedMusic' => $foreign]))
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

it('keeps somebody else\'s deck out of the remote\'s reach', function () {
    $deck = deckWithRoomForASong();

    actingAs(User::factory()->create());

    getJson(route('projections.music-search', ['projection' => $deck->projection, 'q' => 'x']))->assertNotFound();
    postJson(route('projections.added-musics.store', ['projection' => $deck->projection]), ['music_id' => $deck->birthday->id])->assertNotFound();
    postJson(route('projections.move', ['projection' => $deck->projection]), ['kind' => 'slot', 'id' => $deck->communion->id, 'direction' => -1])->assertNotFound();
});

/*
 * ---------------------------------------------------------------------------
 * Moving from the remote.
 * ---------------------------------------------------------------------------
 */

it('refuses every move the outline refuses', function () {
    $deck = deckWithRoomForASong();

    actingAs($deck->user);

    $move = fn (string $kind, int $id, int $direction) => postJson(route('projections.move', ['projection' => $deck->projection]), compact('kind', 'id', 'direction'));

    // A row alone in its music has nowhere to go without leaving it.
    $move('entry', $deck->gloriaRow->id, -1)->assertUnprocessable();
    // A music alone in its slot cannot leave the slot.
    $move('music', $deck->gloriaRow->music_plan_slot_assignment_id, 1)->assertUnprocessable();
    // Nothing before the first slot but a slot with nothing in it.
    $move('slot', $deck->gloria->id, -1)->assertUnprocessable();
    // An empty slot of the plan does not move at all.
    $move('slot', $deck->offertory->id, 1)->assertUnprocessable();

    $move('slot', $deck->communion->id, -1)->assertOk();

    expect($deck->projection->entries()->pluck('id')->all())->toBe([$deck->communionRow->id, $deck->gloriaRow->id]);
});

it('moves an added music across whole slots from the remote', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'sequence' => 2]);

    actingAs($deck->user);

    postJson(route('projections.move', ['projection' => $deck->projection]), ['kind' => 'added', 'id' => $added->id, 'direction' => -1])
        ->assertOk();

    expect($added->fresh()->sequence)->toBe(1);
});

it('says of every node exactly the moves the endpoint accepts', function () {
    $deck = deckWithRoomForASong();
    $added = ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'music_plan_slot_plan_id' => $deck->communion->id]);
    ProjectionSlide::factory()->create(['projection_id' => $deck->projection->id, 'score_id' => $deck->birthdayScore->id, 'added_music_id' => $added->id, 'music_plan_slot_plan_id' => $deck->communion->id, 'sequence' => 2]);
    ProjectionSlide::factory()->text()->create(['projection_id' => $deck->projection->id, 'sequence' => 3]);
    ProjectionMusic::factory()->create(['projection_id' => $deck->projection->id, 'music_id' => $deck->birthday->id, 'sequence' => 1]);

    actingAs($deck->user);

    $outline = app(ProjectionRenderPayload::class)->outlineFor($deck->projection, $deck->projection->entries()->get(), $deck->user);

    $nodes = [];
    $walk = function (array $children) use (&$walk, &$nodes): void {
        foreach ($children as $node) {
            $nodes[] = match ($node['kind']) {
                'entry' => ['entry', $node['entryId'], $node],
                'slot' => ['slot', $node['id'], $node],
                default => $node['local'] ? ['added', $node['addedMusicId'], $node] : ['music', $node['assignmentId'], $node],
            };

            $walk($node['children'] ?? []);
        }
    };
    $walk($outline);

    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as [$kind, $id, $node]) {
        foreach ([-1 => 'canMoveUp', 1 => 'canMoveDown'] as $direction => $flag) {
            DB::beginTransaction();

            $status = postJson(route('projections.move', ['projection' => $deck->projection]), compact('kind', 'id', 'direction'))->status();

            DB::rollBack();

            expect($status === 200)->toBe($node[$flag], "{$kind}:{$id} {$flag}");
        }
    }
});

it('leaves the wall on the slide it was showing when that row is moved', function () {
    $deck = deckWithRoomForASong();
    $presentation = Presentation::factory()->create([
        'projection_id' => $deck->projection->id,
        'user_id' => $deck->user->id,
        'entry_id' => $deck->communionRow->id,
        'slide_index' => 1,
        'entry_sequence' => 1,
    ]);

    actingAs($deck->user);

    postJson(route('projections.move', ['projection' => $deck->projection]), ['kind' => 'slot', 'id' => $deck->communion->id, 'direction' => -1])
        ->assertOk();

    expect($presentation->fresh()->addressIn($deck->projection->entries()->get()))
        ->toBe(['entryId' => $deck->communionRow->id, 'slideIndex' => 1]);
});
