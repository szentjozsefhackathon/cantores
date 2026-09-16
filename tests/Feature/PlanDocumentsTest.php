<?php

use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\PlanDocuments;
use App\Livewire\Pages\ProjectionEditor;
use App\Models\Booklet;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function planWithCelebration(User $user, string $celebration = 'Advent I.'): MusicPlan
{
    return MusicPlan::factory()->create([
        'user_id' => $user->id,
        'celebration_id' => Celebration::factory()->create(['name' => $celebration])->id,
    ]);
}

it('lists a service once, with its booklets and its projections together', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user, 'Advent I.');

    Booklet::factory()->forPlan($plan)->create(['title' => 'Zenekari füzet']);
    Booklet::factory()->forPlan($plan)->create(['title' => 'Kántor füzet']);
    Projection::factory()->forPlan($plan)->create(['title' => 'Nagyterem 16:9']);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->assertSee('Advent I.')
        ->assertSee('Zenekari füzet')
        ->assertSee('Kántor füzet')
        ->assertSee('Nagyterem 16:9');
});

it('lists every plan of the viewer\'s own, built or not, and leaves out other people\'s plans and documents', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $unused = planWithCelebration($mine, 'Üres terv');
    $used = planWithCelebration($mine, 'Karácsony');
    Booklet::factory()->forPlan($used)->create(['title' => 'Karácsonyi füzet']);

    $notMine = planWithCelebration($theirs, 'Idegen alkalom');
    Projection::factory()->forPlan($notMine)->create(['title' => 'Idegen vetítés']);

    actingAs($mine);

    Livewire::test(PlanDocuments::class)
        ->assertSee('Karácsonyi füzet')
        ->assertSee('plan-group-'.$used->id, escape: false)
        ->assertSee('plan-group-'.$unused->id, escape: false)
        ->assertDontSee('plan-group-'.$notMine->id, escape: false)
        ->assertDontSee('Idegen alkalom')
        ->assertDontSee('Idegen vetítés');
});

it('shows documents started without a plan in their own group', function () {
    $user = User::factory()->create();

    Booklet::factory()->create(['user_id' => $user->id, 'title' => 'Terv nélküli füzet']);
    Projection::factory()->create(['user_id' => $user->id, 'title' => 'Terv nélküli vetítés']);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->assertSee(__('Without a plan'))
        ->assertSee('Terv nélküli füzet')
        ->assertSee('Terv nélküli vetítés');
});

it('starts whichever of the two the picker was opened for', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->call('setNewType', 'projection')
        ->call('createFromPlan', $plan->id)
        ->assertRedirect();

    expect(Projection::query()->where('music_plan_id', $plan->id)->exists())->toBeTrue()
        ->and(Booklet::query()->where('music_plan_id', $plan->id)->exists())->toBeFalse();

    Livewire::test(PlanDocuments::class)
        ->call('setNewType', 'booklet')
        ->call('createFromPlan', $plan->id)
        ->assertRedirect();

    expect(Booklet::query()->where('music_plan_id', $plan->id)->exists())->toBeTrue();
});

it('deletes either kind from the list', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user);
    $booklet = Booklet::factory()->forPlan($plan)->create();
    $projection = Projection::factory()->forPlan($plan)->create();

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->call('deleteBooklet', $booklet->id)
        ->call('deleteProjection', $projection->id);

    expect(Booklet::query()->find($booklet->id))->toBeNull()
        ->and(Projection::query()->find($projection->id))->toBeNull();
});

it('refuses to delete someone else\'s document', function () {
    $mine = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($mine);

    Livewire::test(PlanDocuments::class)
        ->call('deleteBooklet', $booklet->id)
        ->assertForbidden();

    expect(Booklet::query()->find($booklet->id))->not->toBeNull();
});

it('copies a booklet from the list and goes straight to the copy', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user);
    $booklet = Booklet::factory()->forPlan($plan)->create(['title' => 'Zenekari füzet', 'page_size' => 'a5']);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->call('duplicateBooklet', $booklet->id)
        ->assertRedirect();

    $copy = Booklet::query()->where('id', '!=', $booklet->id)->where('music_plan_id', $plan->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->title)->toBe(__(':title (copy)', ['title' => 'Zenekari füzet']))
        ->and($copy->page_size->value)->toBe('a5')
        ->and($copy->user_id)->toBe($user->id);
});

it('copies a booklet\'s entries along with it', function () {
    $user = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => $user->id]);
    $booklet->entries()->create(['text' => 'Bevezető', 'sequence' => 1]);

    actingAs($user);

    Livewire::test(PlanDocuments::class)->call('duplicateBooklet', $booklet->id);

    $copy = Booklet::query()->where('id', '!=', $booklet->id)->where('user_id', $user->id)->first();

    expect($copy->entries)->toHaveCount(1)
        ->and($copy->entries->first()->text)->toBe('Bevezető');
});

it('refuses to copy someone else\'s booklet', function () {
    $mine = User::factory()->create();
    $booklet = Booklet::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($mine);

    Livewire::test(PlanDocuments::class)
        ->call('duplicateBooklet', $booklet->id)
        ->assertForbidden();

    expect(Booklet::query()->where('user_id', $mine->id)->count())->toBe(0);
});

it('copies a projection from the list and goes straight to the copy', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Nagyterem 16:9', 'ratio' => '16/9']);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->call('duplicateProjection', $projection->id)
        ->assertRedirect();

    $copy = Projection::query()->where('id', '!=', $projection->id)->where('user_id', $user->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->title)->toBe(__(':title (copy)', ['title' => 'Nagyterem 16:9']))
        ->and($copy->ratio->value)->toBe('16/9');
});

it('refuses to copy someone else\'s projection', function () {
    $mine = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => User::factory()->create()->id]);

    actingAs($mine);

    Livewire::test(PlanDocuments::class)
        ->call('duplicateProjection', $projection->id)
        ->assertForbidden();

    expect(Projection::query()->where('user_id', $mine->id)->count())->toBe(0);
});

it('offers a create button inside each empty column, addressed to that plan', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user, 'Üres terv');

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->assertSeeHtml('action="'.route('booklets.store').'"')
        ->assertSeeHtml('action="'.route('projections.store').'"')
        ->assertSeeHtml('value="'.$plan->id.'"');
});

it('disables the projection screen and remote until something is actually live', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->assertSee('aria-disabled="true"', escape: false)
        ->assertDontSee('Currently projecting');
});

it('enables the projection screen and remote, and names the live deck, once one is up', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Nagyterem 16:9']);
    Presentation::resumeFor($projection, $user);

    actingAs($user);

    Livewire::test(PlanDocuments::class)
        ->assertDontSee('aria-disabled="true"', escape: false)
        ->assertSee(__('Currently projecting'))
        ->assertSee('Nagyterem 16:9')
        ->assertSee(__('Edit deck'))
        ->assertSee(__('Remove from screen'));
});

/*
 * The fast way out of a deck started by mistake: take it off every screen
 * showing it, and end it, from the one page a cantor is already looking at.
 */
it('removes the live deck from every screen showing it and ends the projection', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id, 'title' => 'Rossz oldalarány']);
    $presentation = Presentation::resumeFor($projection, $user);

    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'presentation_id' => $presentation->id,
        'last_seen_at' => Carbon::now(),
    ]);

    actingAs($user);

    Livewire::test(PlanDocuments::class)->call('removeFromScreen');

    expect($presentation->fresh()->ended_at)->not->toBeNull()
        ->and($screen->fresh()->presentation_id)->toBeNull();

    Livewire::test(PlanDocuments::class)
        ->assertDontSee(__('Currently projecting'));
});

/*
 * A live row with no screen left pointing at it is rare — a screen aged out or
 * was pointed elsewhere already — but the button still has to end the service
 * rather than do nothing.
 */
it('ends the presentation directly when nothing is left pointing a screen at it', function () {
    $user = User::factory()->create();
    $projection = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = Presentation::resumeFor($projection, $user);

    actingAs($user);

    Livewire::test(PlanDocuments::class)->call('removeFromScreen');

    expect($presentation->fresh()->ended_at)->not->toBeNull();
});

/*
 * The deck a screen shows next opens on the title card again, the same way a
 * freshly opened wall would — this is what "Remove from screen" buys: time to
 * line the projector up before the room sees the replacement.
 */
it('shows the title card again for the next deck put on a screen just cleared', function () {
    $user = User::factory()->create();
    $wrong = Projection::factory()->create(['user_id' => $user->id]);
    $right = Projection::factory()->create(['user_id' => $user->id]);
    $presentation = Presentation::resumeFor($wrong, $user);

    $screen = Screen::factory()->create([
        'user_id' => $user->id,
        'presentation_id' => $presentation->id,
        'last_seen_at' => Carbon::now(),
    ]);

    actingAs($user);

    Livewire::test(PlanDocuments::class)->call('removeFromScreen');

    expect(Presentation::splashFor($screen->fresh()))->toBe(Presentation::SPLASH_CARD);
});

it('sends the two old list screens to the consolidated one', function () {
    actingAs(User::factory()->create());

    get('/booklets')->assertRedirect('/plan-documents');
    get('/projections')->assertRedirect('/plan-documents');
});

// The point of the consolidation: from inside one document, the service's other
// documents are one press away.
it('offers the service\'s other documents at the top of each editor', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user);
    $booklet = Booklet::factory()->forPlan($plan)->create(['title' => 'Zenekari füzet']);
    $projection = Projection::factory()->forPlan($plan)->create(['title' => 'Nagyterem 16:9']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSee('Nagyterem 16:9')
        ->assertSee(route('projections.edit', ['projection' => $projection->id]), escape: false);

    Livewire::test(ProjectionEditor::class, ['projection' => $projection])
        ->assertSee('Zenekari füzet')
        ->assertSee(route('booklets.edit', ['booklet' => $booklet->id]), escape: false);
});

it('keeps another person\'s documents out of that switcher', function () {
    $user = User::factory()->create();
    $plan = planWithCelebration($user);
    $booklet = Booklet::factory()->forPlan($plan)->create(['title' => 'Zenekari füzet']);
    Projection::factory()->create([
        'user_id' => User::factory()->create()->id,
        'music_plan_id' => $plan->id,
        'title' => 'Idegen vetítés',
    ]);
    Projection::factory()->forPlan($plan)->create(['title' => 'Nagyterem 16:9']);

    actingAs($user);

    Livewire::test(BookletEditor::class, ['booklet' => $booklet])
        ->assertSee('Nagyterem 16:9')
        ->assertDontSee('Idegen vetítés');
});
