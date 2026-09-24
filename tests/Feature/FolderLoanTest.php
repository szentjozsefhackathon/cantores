<?php

use App\Livewire\LoanLinks;
use App\Livewire\Pages\FolderEditor;
use App\Livewire\Pages\FolderView;
use App\Models\Folder;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// What the link opens, not who may open it — the Turnstile gate is tested apart.
beforeEach(function () {
    passHumanCheck();
});

it('returns 404 for an unknown folder token', function () {
    get(route('folder.loan', ['token' => 'nonexistenttoken12345678901234']))->assertNotFound();
});

it('redirects the owner to the edit screen', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(FolderView::class, ['token' => $loan->token])
        ->assertRedirect(route('folders.edit', ['folder' => $folder->id]));
});

it('shows read-only view to a guest', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    get(route('folder.loan', ['token' => $loan->token]))->assertOk();
});

it('shows read-only view to another authenticated user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id, 'name' => 'Advent']);
    $loan = Loan::factory()->of($folder)->create();

    actingAs($other);

    Livewire::test(FolderView::class, ['token' => $loan->token])
        ->assertSet('name', 'Advent');
});

it('owner can generate a secret link', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    expect($folder->loanToken())->toBeNull();

    Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->call('lend')
        ->assertHasNoErrors();

    expect($folder->fresh()->loanToken())->not->toBeNull()->toHaveLength(32);
});

it('secret link resolves to a valid URL after generation', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    actingAs($user);

    Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->call('lend');

    $token = $folder->fresh()->loanToken();
    expect($token)->not->toBeNull();

    Auth::logout();
    get(route('folder.loan', ['token' => $token]))->assertOk();
});

it('owner can delete the secret link', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->of($folder)->create();
    actingAs($user);

    Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->call('recall', $loan->id)
        ->assertHasNoErrors();

    expect($folder->fresh()->loanToken())->toBeNull();
});

it('deleted secret link returns 404', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $loan = Loan::factory()->of($folder)->create();
    $token = $loan->token;
    actingAs($user);

    Livewire::test(LoanLinks::class, ['lendable' => $folder])
        ->call('recall', $loan->id);

    get(route('folder.loan', ['token' => $token]))->assertNotFound();
});

it('another user cannot open FolderEditor for someone elses folder', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    actingAs($other);

    Livewire::test(FolderEditor::class, ['folder' => $folder])
        ->assertForbidden();
});

it('read-only view has noindex meta tag', function () {
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $loan = Loan::factory()->of($folder)->create();

    get(route('folder.loan', ['token' => $loan->token]))
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});
