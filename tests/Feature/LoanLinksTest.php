<?php

use App\Livewire\LoanLinks;
use App\Models\Folder;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * One folder, lent two ways at once: an open link handed round for a week, and a
 * standing one for the band. Each is named and recalled on its own.
 */
it('recalls one link and leaves the others open', function () {
    passHumanCheck();

    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $week = Loan::factory()->of($folder)->create(['label' => 'Egy hét']);
    $band = Loan::factory()->of($folder)->restrictedTo([User::factory()->create()])->create(['label' => 'Zenekar']);

    actingAs($owner);

    Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->assertSee(['Egy hét', 'Zenekar'])
        ->call('recall', $week->id)
        ->assertSee($band->url())
        ->assertDontSee($week->url());

    expect($week->fresh()->revoked_at)->not->toBeNull()
        ->and($band->fresh()->isLive())->toBeTrue();

    auth()->logout();

    get($week->url())->assertNotFound();
});

it('names a link, and clears the name when left blank', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    actingAs($owner);

    $component = Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->call('rename', $loan->id, '  Zenekar  ');

    expect($loan->fresh()->label)->toBe('Zenekar');

    $component->call('rename', $loan->id, ' ');

    expect($loan->fresh()->label)->toBeNull();
});

it('lets nobody but the lender recall or rename a link', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    actingAs(User::factory()->create());

    Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->call('recall', $loan->id)
        ->assertForbidden();

    expect($loan->fresh()->isLive())->toBeTrue();
});

it('does not reach a link lent from something else', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $other = Loan::factory()->of(Folder::factory()->create(['user_id' => $owner->id]))->create();

    actingAs($owner);

    expect(fn () => Livewire::test(LoanLinks::class, ['lendable' => $folder])->call('recall', $other->id))
        ->toThrow(ModelNotFoundException::class);

    expect($other->fresh()->isLive())->toBeTrue();
});
