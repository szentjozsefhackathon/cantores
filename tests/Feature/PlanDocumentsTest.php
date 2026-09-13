<?php

use App\Livewire\Pages\BookletEditor;
use App\Livewire\Pages\PlanDocuments;
use App\Livewire\Pages\ProjectionEditor;
use App\Models\Booklet;
use App\Models\Celebration;
use App\Models\MusicPlan;
use App\Models\Projection;
use App\Models\User;
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

it('leaves out plans nothing was built from, and other people\'s documents', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $unused = planWithCelebration($mine, 'Üres terv');
    $used = planWithCelebration($mine, 'Karácsony');
    Booklet::factory()->forPlan($used)->create(['title' => 'Karácsonyi füzet']);

    $notMine = planWithCelebration($theirs, 'Idegen alkalom');
    Projection::factory()->forPlan($notMine)->create(['title' => 'Idegen vetítés']);

    actingAs($mine);

    // The empty plan is still offered in the new-document picker; what it must
    // not have is a row of its own.
    Livewire::test(PlanDocuments::class)
        ->assertSee('Karácsonyi füzet')
        ->assertSee('plan-group-'.$used->id, escape: false)
        ->assertDontSee('plan-group-'.$unused->id, escape: false)
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
