<?php

use App\Models\Loan;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * A presentation is the row on which a laptop at the back of a church and a
 * phone at the organ agree about where the service has got to. What there is to
 * check is that the agreement holds under the things that actually happen on a
 * Sunday morning: two people pressing at once, a bad connection answering late,
 * and a deck being edited while it is being shown.
 */

function presentationFor(User $user, Projection $projection): Presentation
{
    return Presentation::factory()->create([
        'projection_id' => $projection->id,
        'user_id' => $user->id,
    ]);
}

it('carries where the service is between two devices', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 2,
        'blanked' => true,
    ])->assertOk();

    getJson(route('presentations.state', $presentation))
        ->assertOk()
        ->assertJson([
            'entryId' => $entry->id,
            'slideIndex' => 2,
            'blanked' => true,
        ]);
});

/*
 * Without this, a read answered just before the cantor pressed space arrives
 * just after it and sends the room back a slide.
 */
it('moves the version forward on every change and never back', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    $versions = collect([0, 1, 2, 3])->map(fn (int $slide): int => postJson(
        route('presentations.state.store', $presentation),
        ['entryId' => $entry->id, 'slideIndex' => $slide],
    )->json('version'));

    expect($versions->all())->toBe($versions->sort()->values()->all())
        ->and($versions->unique())->toHaveCount(4);
});

/*
 * The heartbeat is the other half of that rule. A screen saying "still here, and
 * still showing this" is not an event, and a version bumped for it would reach
 * the phone a beat after a tap as though the wall had contradicted it.
 */
it('leaves the version alone when a heartbeat says nothing new', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    $moved = postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 1,
    ])->json('version');

    $beat = postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 1,
        'drawnRevision' => $projection->revision(),
    ]);

    expect($beat->json('version'))->toBe($moved)
        ->and($beat->json('drawnRevision'))->toBe($projection->revision());
});

// A poll that finds the service where it left it is answered with nothing; a
// write is always answered in full, because the writer needs the version back.
it('answers an unchanged state with an empty 304, and a moved one in full', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = presentationFor($user, $projection);

    actingAs($user);

    $etag = getJson(route('presentations.state', $presentation))->assertOk()->headers->get('ETag');

    getJson(route('presentations.state', $presentation), ['If-None-Match' => $etag])->assertStatus(304);

    postJson(route('presentations.state.store', $presentation), ['blanked' => true], ['If-None-Match' => $etag])
        ->assertOk()
        ->assertHeaderMissing('ETag');

    getJson(route('presentations.state', $presentation), ['If-None-Match' => $etag])
        ->assertOk()
        ->assertJsonPath('blanked', true);
});

// A service somebody else is running is not a thing this account may know exists.
it('answers 404 for a presentation that is not yours', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $owner->id]);
    $presentation = presentationFor($owner, $projection);

    actingAs($stranger);

    getJson(route('presentations.state', $presentation))->assertNotFound();
    postJson(route('presentations.state.store', $presentation), ['slideIndex' => 1])->assertNotFound();
    getJson(route('presentations.payload', $presentation))->assertNotFound();
});

/*
 * Entitlement is resolved on every payload read rather than carried over, so a
 * loan recalled between Thursday and Sunday empties the deck on the phone at the
 * same moment it empties it on the wall.
 */
it('drops a score from the payload once the loan under it is recalled', function () {
    $owner = User::factory()->create();
    $cantor = User::factory()->create();

    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->create([
        'user_id' => $owner->id,
        'lendable_id' => $score->id,
        'lendable_type' => Score::class,
    ]);
    ReceivedLoan::factory()->kept()->create(['user_id' => $cantor->id, 'loan_id' => $loan->id]);

    $projection = Projection::factory()->create(['user_id' => $cantor->id]);
    ProjectionSlide::factory()->create(['projection_id' => $projection->id, 'score_id' => $score->id]);

    $presentation = presentationFor($cantor, $projection);

    actingAs($cantor);

    expect(getJson(route('presentations.payload', $presentation))->json('entries'))->toHaveCount(1);

    $loan->forceFill(['revoked_at' => Carbon::now()])->save();

    expect(getJson(route('presentations.payload', $presentation))->json('entries'))->toBe([]);
});

/*
 * The half hour before a service is when a deck changes most, and both clients
 * engraved theirs once at load. The revision is what tells them to read it
 * again — and it is read off the rows, so nothing has to remember to raise it.
 */
it('moves the revision when the projection, a row or a score behind a row is saved', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    $atLoad = $projection->revision();

    // Nothing was saved, so nothing moved.
    expect($projection->fresh()->revision())->toBe($atLoad);

    Carbon::setTestNow(Carbon::now()->addMinute());
    $score->forceFill(['content' => "X:1\nK:C\nc d e f|\n"])->save();
    $afterScore = $projection->fresh()->revision();
    expect($afterScore)->toBeGreaterThan($atLoad);

    Carbon::setTestNow(Carbon::now()->addMinute());
    $entry->forceFill(['sequence' => 3])->save();
    $afterRow = $projection->fresh()->revision();
    expect($afterRow)->toBeGreaterThan($afterScore);

    Carbon::setTestNow(Carbon::now()->addMinute());
    $projection->forceFill(['title' => 'Advent 1.'])->save();
    expect($projection->fresh()->revision())->toBeGreaterThan($afterRow);

    Carbon::setTestNow();
});

/*
 * Entitlement is deliberately outside the fingerprint: a recalled loan is not an
 * edit to the deck, and it must never take the picture off the wall by itself in
 * the middle of a Mass. It takes effect on the next re-engraving for any reason.
 */
it('leaves the revision alone when only a loan is recalled', function () {
    $owner = User::factory()->create();
    $cantor = User::factory()->create();

    $score = Score::factory()->abc()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->create([
        'user_id' => $owner->id,
        'lendable_id' => $score->id,
        'lendable_type' => Score::class,
    ]);
    ReceivedLoan::factory()->kept()->create(['user_id' => $cantor->id, 'loan_id' => $loan->id]);

    $projection = Projection::factory()->create(['user_id' => $cantor->id]);
    ProjectionSlide::factory()->create(['projection_id' => $projection->id, 'score_id' => $score->id]);

    $before = $projection->revision();

    $loan->forceFill(['revoked_at' => Carbon::now()])->save();

    expect($projection->fresh()->revision())->toBe($before);
});

/*
 * The address is resolved forgivingly against the deck as it now stands: an edit
 * made during the rehearsal must not cost the place in the service.
 */
it('resolves an address across a deleted row', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $rows = collect([0, 1, 2])->map(fn (int $sequence): ProjectionSlide => ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
        'sequence' => $sequence,
    ]));

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $rows[1]->id,
        'slideIndex' => 2,
    ])->assertOk();

    $rows[1]->delete();

    // The service lands on the first slide of the next row that survived.
    getJson(route('presentations.state', $presentation))
        ->assertJson(['entryId' => $rows[2]->id, 'slideIndex' => 0]);

    // And on the end of the deck when nothing follows it.
    $rows[2]->delete();

    getJson(route('presentations.state', $presentation))
        ->assertJson(['entryId' => $rows[0]->id, 'slideIndex' => Presentation::LAST_SLIDE]);
});

/*
 * Which slide within a row is never resolved here: how many slides a row comes
 * to is read off the score in the browser every time the deck is drawn, so the
 * clamping is the client's and the address travels untouched.
 */
it('hands the slide within a surviving row back untouched', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
    ]);

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'slideIndex' => 7,
    ])->assertOk();

    getJson(route('presentations.state', $presentation))
        ->assertJson(['entryId' => $entry->id, 'slideIndex' => 7]);
});

/*
 * A verse brought back is today's deviation, not an edit of the deck — so it
 * lives on the presentation, and means nothing once the row it names is gone.
 */
it('prunes a revealed verse with the row it named', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $kept = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
        'sequence' => 0,
    ]);

    $gone = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => Score::factory()->abc()->create(['user_id' => $user->id])->id,
        'sequence' => 1,
    ]);

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    postJson(route('presentations.state.store', $presentation), [
        'reveals' => [$kept->id => [1], $gone->id => [0]],
    ])->assertOk();

    $gone->delete();

    expect(getJson(route('presentations.state', $presentation))->json('reveals'))
        ->toBe([(string) $kept->id => [1]]);
});

/*
 * Live means heard from lately and not deliberately ended. Both, because the two
 * ways a service stops — a tab closed without warning, and a cantor who says so
 * — leave different traces.
 */
it('ages a presentation out of the live scope on last_seen_at', function () {
    $user = User::factory()->create();

    $running = Presentation::factory()->create(['user_id' => $user->id]);
    Presentation::factory()->stale()->create();
    Presentation::factory()->ended()->create(['user_id' => $user->id]);

    expect(Presentation::query()->live()->pluck('id')->all())->toBe([$running->id])
        ->and($running->isLive())->toBeTrue();
});

/*
 * Two windows on one deck are one presentation, which is the whole mechanism
 * this feature is built out of: the phone is only a third client of the same row.
 */
it('rejoins the show rather than starting a second when the same deck is put up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);

    $first = Presentation::putUp($user, $projection);
    $second = Presentation::putUp($user, $projection);

    expect($second->id)->toBe($first->id)
        ->and(Presentation::query()->where('projection_id', $projection->id)->count())->toBe(1);

    $first->end();

    expect(Presentation::putUp($user, $projection)->id)->not->toBe($first->id);
});

/*
 * The honest answer to "is the room seeing my edit yet". The wall reports the
 * deck it has actually finished engraving, not the one it has heard of, so
 * between the save and the re-engraving the phone can say the wall is behind
 * instead of implying the room already sees what the phone sees.
 */
it('reports drawn_revision behind revision until the wall has caught up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    $entry = ProjectionSlide::factory()->create([
        'projection_id' => $projection->id,
        'score_id' => $score->id,
    ]);

    $presentation = presentationFor($user, $projection);

    actingAs($user);

    // The wall has drawn the deck as it stood when it opened.
    $atLoad = $projection->revision();
    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'drawnRevision' => $atLoad,
    ])->assertOk();

    // Somebody retypes a stanza in the editor on another screen.
    Carbon::setTestNow(Carbon::now()->addMinute());
    $score->forceFill(['content' => "X:1\nK:C\nc d e f|\n"])->save();

    $behind = getJson(route('presentations.state', $presentation))->json();

    expect($behind['drawnRevision'])->toBe($atLoad)
        ->and($behind['revision'])->not->toBe($atLoad);

    // The wall reads the deck again, engraves it, and says so.
    postJson(route('presentations.state.store', $presentation), [
        'entryId' => $entry->id,
        'drawnRevision' => $behind['revision'],
    ])->assertOk();

    $caughtUp = getJson(route('presentations.state', $presentation))->json();

    expect($caughtUp['drawnRevision'])->toBe($caughtUp['revision']);

    Carbon::setTestNow();
});
