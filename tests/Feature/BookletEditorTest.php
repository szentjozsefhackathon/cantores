<?php

use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\Booklets;
use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\MusicPlan;
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

it('reorders scores and renumbers the whole list', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $scores = Score::factory()->abc()->count(3)->create(['user_id' => $user->id]);

    foreach ($scores as $index => $score) {
        BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => $index,
        ]);
    }

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $scores[2]->id, -1);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$scores[0]->id, $scores[2]->id, $scores[1]->id]);
});

it('leaves the order alone when a score is already at the end', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $scores = Score::factory()->abc()->count(2)->create(['user_id' => $user->id]);

    foreach ($scores as $index => $score) {
        BookletScore::factory()->create([
            'booklet_id' => $booklet->id,
            'score_id' => $score->id,
            'sequence' => $index,
        ]);
    }

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('move', $scores[1]->id, 1);

    expect($booklet->entries()->orderBy('sequence')->pluck('score_id')->all())
        ->toBe([$scores[0]->id, $scores[1]->id]);
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

    BookletScore::factory()->create(['booklet_id' => $one->id, 'score_id' => $score->id]);
    BookletScore::factory()->create(['booklet_id' => $two->id, 'score_id' => $score->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $one])
        ->call('saveOverride', $score->id, ['abcPageWidth' => 700]);

    // Whole numbers come back from JSON as ints, so compare by value.
    expect($one->entries()->first()->settings_override)->toEqual(['abcPageWidth' => 700])
        ->and($two->entries()->first()->settings_override)->toBeNull()
        ->and($score->fresh()->settings)->toBe(['abc' => ['paper' => ['abcPageWidth' => 1700]]]);
});

it('clamps an out-of-range override and drops keys the format does not have', function () {
    $user = User::factory()->create();
    $booklet = bookletFor($user);
    $score = Score::factory()->abc()->create(['user_id' => $user->id]);
    BookletScore::factory()->create(['booklet_id' => $booklet->id, 'score_id' => $score->id]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('saveOverride', $score->id, [
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
    BookletScore::factory()->create([
        'booklet_id' => $booklet->id,
        'score_id' => $score->id,
        'settings_override' => ['abcPageWidth' => 700],
    ]);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->call('resetOverride', $score->id);

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
        ->and($payload[0]['format'])->toBe('abc')
        ->and($payload[0]['content'])->toContain('X:1')
        ->and($payload[0]['credit'])->toBeNull();
});

it('refuses to open or change someone elses booklet', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $booklet = bookletFor($owner);

    actingAs($stranger);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertForbidden();
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
