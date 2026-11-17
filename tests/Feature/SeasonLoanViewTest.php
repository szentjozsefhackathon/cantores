<?php

use App\Enums\AttendanceStatus;
use App\Livewire\Pages\SeasonLoanView;
use App\Models\Booklet;
use App\Models\Loan;
use App\Models\Season;
use App\Models\SeasonAttendance;
use App\Models\SeasonEvent;
use App\Models\SeasonMember;
use App\Models\User;
use App\Services\SeasonSharingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The season on the link the choir gets: a name, and that name's answers.
 */
beforeEach(function () {
    passHumanCheck();
});

/**
 * @return array{Season, Loan, SeasonEvent}
 */
function sharedSeasonWithEvent(): array
{
    $season = Season::factory()->create(['name' => 'Ferences Schola 2026-2027']);
    $loan = Loan::factory()->of($season)->create();
    $event = SeasonEvent::factory()->create(['season_id' => $season->id, 'name' => 'Éjféli mise']);

    return [$season, $loan, $event];
}

it('opens the season on its link', function () {
    [, $loan] = sharedSeasonWithEvent();

    get($loan->url())
        ->assertOk()
        ->assertSee('Ferences Schola 2026-2027')
        ->assertSee('Éjféli mise');
});

it('answers 404 for an unknown or recalled link', function () {
    [, $loan] = sharedSeasonWithEvent();
    $loan->revoke();

    get(route('season.loan', ['token' => $loan->token]))->assertNotFound();
    get(route('season.loan', ['token' => str_repeat('x', 32)]))->assertNotFound();
});

it('sends a guest to sign in when the link is for named people only', function () {
    $season = Season::factory()->create();
    $loan = Loan::factory()->of($season)->restrictedTo([User::factory()->create()])->create();

    get($loan->url())->assertRedirect(route('login'));
});

it('lets a guest add their name and answer, and take the answer back', function () {
    [$season, $loan, $event] = sharedSeasonWithEvent();

    $component = Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->set('newName', '  Kovács   Anna ')
        ->call('join')
        ->assertHasNoErrors()
        ->call('respond', $event->id, 'maybe');

    $member = SeasonMember::query()->sole();

    expect($member->name)->toBe('Kovács Anna')
        ->and($member->season_id)->toBe($season->id)
        ->and(SeasonAttendance::query()->sole()->status)->toBe(AttendanceStatus::Maybe);

    $component->call('respond', $event->id, 'maybe');

    expect(SeasonAttendance::query()->count())->toBe(0);
});

it('answers as the existing member when the same name is typed in another case', function () {
    [$season, $loan] = sharedSeasonWithEvent();
    $anna = SeasonMember::factory()->create(['season_id' => $season->id, 'name' => 'Kovács Anna']);

    Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->set('newName', 'kovács anna')
        ->call('join')
        ->assertSet('memberId', $anna->id);

    expect(SeasonMember::query()->count())->toBe(1);
});

it('recognises the browser from its cookie', function () {
    [$season, $loan] = sharedSeasonWithEvent();
    $anna = SeasonMember::factory()->create(['season_id' => $season->id, 'name' => 'Kovács Anna']);

    Livewire::withCookie('season_member_'.$season->id, (string) $anna->id)
        ->test(SeasonLoanView::class, ['token' => $loan->token])
        ->assertSet('memberId', $anna->id);
});

it('ignores a cookie naming another season\'s member', function () {
    [$season, $loan] = sharedSeasonWithEvent();
    $stranger = SeasonMember::factory()->create();

    Livewire::withCookie('season_member_'.$season->id, (string) $stranger->id)
        ->test(SeasonLoanView::class, ['token' => $loan->token])
        ->assertSet('memberId', null);
});

it('recognises a signed-in member on any device', function () {
    [$season, $loan] = sharedSeasonWithEvent();
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->set('newName', 'Kovács Anna')
        ->call('join');

    $member = SeasonMember::query()->sole();

    expect($member->user_id)->toBe($user->id);

    Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->assertSet('memberId', $member->id);
});

it('does not answer for another season\'s date', function () {
    [, $loan] = sharedSeasonWithEvent();
    $foreignEvent = SeasonEvent::factory()->create();

    $component = Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->set('newName', 'Kovács Anna')
        ->call('join');

    expect(fn () => $component->call('respond', $foreignEvent->id, 'yes'))
        ->toThrow(ModelNotFoundException::class);

    expect(SeasonAttendance::query()->count())->toBe(0);
});

it('ignores an answer that is not one of the three', function () {
    [, $loan, $event] = sharedSeasonWithEvent();

    Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->set('newName', 'Kovács Anna')
        ->call('join')
        ->call('respond', $event->id, 'definitely');

    expect(SeasonAttendance::query()->count())->toBe(0);
});

it('stops taking answers once the link is recalled', function () {
    [, $loan, $event] = sharedSeasonWithEvent();

    $component = Livewire::test(SeasonLoanView::class, ['token' => $loan->token])
        ->set('newName', 'Kovács Anna')
        ->call('join');

    $loan->revoke();

    $component->call('respond', $event->id, 'yes')->assertNotFound();

    expect(SeasonAttendance::query()->count())->toBe(0);
});

it('links an attached booklet through the loan the season keeps for it', function () {
    [$season, $loan, $event] = sharedSeasonWithEvent();
    $booklet = Booklet::factory()->create(['user_id' => $season->user_id, 'title' => 'Karácsonyi füzet']);

    $attachment = app(SeasonSharingService::class)->attach($event, $booklet);

    get($loan->url())
        ->assertOk()
        ->assertSee('Karácsonyi füzet')
        ->assertSee($attachment->loan->url(), false);
});
