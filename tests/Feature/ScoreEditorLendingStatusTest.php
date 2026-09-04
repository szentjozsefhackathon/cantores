<?php

use App\Livewire\Pages\ScoreEditor;
use App\Models\Folder;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\Loan;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('calls an unshared score private', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Private — only you can see it'))
        ->assertDontSee(__('Shared with a secret link'))
        ->assertDontSee(__('In the public library'));
});

it('reports a secret link on the score', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    Loan::factory()->of($score)->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Shared with a secret link'))
        ->assertDontSee(__('Private — only you can see it'));
});

it('reports a share reaching the score through a folder', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $folder->scores()->attach($score);
    Loan::factory()->of($folder)->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Shared through a folder or music plan'))
        ->assertDontSee(__('Private — only you can see it'));
});

it('reports a published score as being in the public library', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    ScorePublication::factory()->of($score)->approved()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('In the public library'))
        ->assertDontSee(__('Private — only you can see it'));
});

it('reports a nomination that is still waiting for a reviewer', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);
    ScorePublication::factory()->of($score)->submitted()->create();

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Waiting for review by an editor'))
        ->assertDontSee(__('Private — only you can see it'));
});

it('does not claim privacy on the guest preview page', function () {
    Livewire::test(ScoreEditor::class)
        ->assertDontSee(__('Private — only you can see it'));
});

it('shows the secret-link badge as soon as one is generated', function () {
    $user = User::factory()->create();
    $score = Score::factory()->unattached()->create(['user_id' => $user->id]);

    actingAs($user);

    Livewire::test(ScoreEditor::class, ['score' => $score])
        ->assertSee(__('Private — only you can see it'))
        ->call('lendByLink')
        ->assertSee(__('Shared with a secret link'))
        ->call('recallLoan')
        ->assertSee(__('Private — only you can see it'));
});
