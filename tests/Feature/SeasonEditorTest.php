<?php

use App\Enums\AttendanceStatus;
use App\Livewire\Pages\SeasonEditor;
use App\Livewire\Pages\Seasons;
use App\Models\Booklet;
use App\Models\Celebration;
use App\Models\Loan;
use App\Models\MusicPlan;
use App\Models\Season;
use App\Models\SeasonAttendance;
use App\Models\SeasonEvent;
use App\Models\SeasonEventAttachment;
use App\Models\SeasonMember;
use App\Models\User;
use App\Services\LiturgicalInfoService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The leader's side of a season: dates, what hangs off them, and the grid of
 * answers. Only the owner reaches any of it.
 */
it('starts a season and opens its editor', function () {
    $owner = User::factory()->create();

    actingAs($owner);

    Livewire::test(Seasons::class)
        ->set('name', 'Ferences Schola 2026-2027')
        ->call('create')
        ->assertRedirect(route('seasons.edit', ['season' => Season::query()->sole()->id]));

    expect(Season::query()->sole())
        ->user_id->toBe($owner->id)
        ->name->toBe('Ferences Schola 2026-2027');
});

it('lists only the viewer\'s own seasons', function () {
    $owner = User::factory()->create();
    Season::factory()->create(['user_id' => $owner->id, 'name' => 'Ferences Schola']);
    Season::factory()->create(['name' => 'Másik kórus']);

    actingAs($owner);

    Livewire::test(Seasons::class)
        ->assertSee('Ferences Schola')
        ->assertDontSee('Másik kórus');
});

it('opens the editor for its owner with the dates, the members and the share links', function () {
    $season = Season::factory()->create(['name' => 'Ferences Schola 2026-2027']);
    Loan::factory()->of($season)->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id, 'name' => 'Éjféli mise']);
    $member = SeasonMember::factory()->create(['season_id' => $season->id, 'name' => 'Kovács Anna']);
    SeasonAttendance::factory()->create(['season_event_id' => $event->id, 'season_member_id' => $member->id]);

    actingAs($season->user);

    get(route('seasons.edit', ['season' => $season]))
        ->assertOk()
        ->assertSee('Ferences Schola 2026-2027')
        ->assertSee('Éjféli mise')
        ->assertSee('Kovács Anna')
        ->assertSee($season->liveLoans()->sole()->url(), false);
});

it('keeps someone else\'s season closed', function () {
    $season = Season::factory()->create();

    actingAs(User::factory()->create());

    get(route('seasons.edit', ['season' => $season]))->assertForbidden();
});

it('adds a date', function () {
    $season = Season::factory()->create();

    actingAs($season->user);

    Livewire::test(SeasonEditor::class, ['season' => $season])
        ->set('eventDate', '2026-12-24')
        ->set('eventTime', '23:30')
        ->set('eventName', 'Éjféli mise')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($season->events()->sole())
        ->name->toBe('Éjféli mise')
        ->timeLabel()->toBe('23:30')
        ->and($season->events()->sole()->date->format('Y-m-d'))->toBe('2026-12-24');
});

it('repeats a rehearsal every week up to the end date', function () {
    $season = Season::factory()->create();

    actingAs($season->user);

    Livewire::test(SeasonEditor::class, ['season' => $season])
        ->set('eventDate', '2026-12-01')
        ->set('eventName', 'Próba')
        ->set('repeatUntil', '2026-12-22')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($season->events()->get()->map(fn (SeasonEvent $event) => $event->date->format('Y-m-d'))->all())
        ->toBe(['2026-12-01', '2026-12-08', '2026-12-15', '2026-12-22']);
});

it('ties a date to one of the day\'s celebrations and names it after it', function () {
    app()->instance(LiturgicalInfoService::class, Mockery::mock(LiturgicalInfoService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getCelebrations')->with('2027-05-16')->andReturn([
            ['name' => 'Pünkösdvasárnap', 'dateISO' => '2027-05-16', 'celebrationKey' => 0, 'colorText' => 'piros'],
        ]);
    }));

    $season = Season::factory()->create();

    actingAs($season->user);

    Livewire::test(SeasonEditor::class, ['season' => $season])
        ->set('eventDate', '2027-05-16')
        ->call('chooseCelebration', 0)
        ->assertSet('eventName', 'Pünkösdvasárnap')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $celebration = Celebration::query()->sole();

    expect($celebration->is_custom)->toBeFalse()
        ->and($season->events()->sole()->celebration_id)->toBe($celebration->id);
});

it('refuses to tie a date to someone else\'s music plan', function () {
    $season = Season::factory()->create();
    $foreignPlan = MusicPlan::factory()->create();

    actingAs($season->user);

    Livewire::test(SeasonEditor::class, ['season' => $season])
        ->set('eventDate', '2026-12-24')
        ->set('eventName', 'Éjféli mise')
        ->set('musicPlanId', (string) $foreignPlan->id)
        ->call('saveEvent')
        ->assertHasErrors('musicPlanId');

    expect($season->events()->count())->toBe(0);
});

it('lends an attached booklet while the season is shared and recalls it when detached', function () {
    $season = Season::factory()->create();
    Loan::factory()->of($season)->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id]);
    $booklet = Booklet::factory()->create(['user_id' => $season->user_id]);

    actingAs($season->user);

    $component = Livewire::test(SeasonEditor::class, ['season' => $season])
        ->call('openAttach', $event->id)
        ->call('attachBooklet', $booklet->id);

    $attachment = SeasonEventAttachment::query()->sole();
    $loan = $attachment->loan;

    expect($loan->lendable->is($booklet))->toBeTrue()
        ->and($loan->isLive())->toBeTrue();

    $component->call('detach', $attachment->id);

    expect($loan->fresh()->isLive())->toBeFalse()
        ->and(SeasonEventAttachment::query()->count())->toBe(0);
});

it('does not attach someone else\'s booklet', function () {
    $season = Season::factory()->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id]);
    $foreignBooklet = Booklet::factory()->create();

    actingAs($season->user);

    $component = Livewire::test(SeasonEditor::class, ['season' => $season])
        ->call('openAttach', $event->id);

    expect(fn () => $component->call('attachBooklet', $foreignBooklet->id))
        ->toThrow(ModelNotFoundException::class);

    expect(SeasonEventAttachment::query()->count())->toBe(0);
});

it('attaches a plain link', function () {
    $season = Season::factory()->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id]);

    actingAs($season->user);

    Livewire::test(SeasonEditor::class, ['season' => $season])
        ->call('openAttach', $event->id)
        ->set('linkUrl', 'javascript:alert(1)')
        ->call('attachLink')
        ->assertHasErrors('linkUrl')
        ->set('linkUrl', 'https://drive.google.com/drive/folders/abc')
        ->set('linkLabel', 'Felvételek')
        ->call('attachLink')
        ->assertHasNoErrors();

    expect(SeasonEventAttachment::query()->sole())
        ->url->toBe('https://drive.google.com/drive/folders/abc')
        ->title()->toBe('Felvételek');
});

it('steps a member\'s answer round when the owner clicks the cell', function () {
    $season = Season::factory()->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id]);
    $member = SeasonMember::factory()->create(['season_id' => $season->id]);

    actingAs($season->user);

    $component = Livewire::test(SeasonEditor::class, ['season' => $season]);
    $answer = fn () => SeasonAttendance::query()->first()?->status;

    $component->call('cycleAnswer', $event->id, $member->id);
    expect($answer())->toBe(AttendanceStatus::Yes);

    $component->call('cycleAnswer', $event->id, $member->id);
    expect($answer())->toBe(AttendanceStatus::Maybe);

    $component->call('cycleAnswer', $event->id, $member->id);
    expect($answer())->toBe(AttendanceStatus::No);

    $component->call('cycleAnswer', $event->id, $member->id);
    expect($answer())->toBeNull();
});

it('does not touch another season\'s members', function () {
    $season = Season::factory()->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id]);
    $foreignMember = SeasonMember::factory()->create();

    actingAs($season->user);

    expect(fn () => Livewire::test(SeasonEditor::class, ['season' => $season])
        ->call('cycleAnswer', $event->id, $foreignMember->id))
        ->toThrow(ModelNotFoundException::class);

    expect(SeasonAttendance::query()->count())->toBe(0);
});
