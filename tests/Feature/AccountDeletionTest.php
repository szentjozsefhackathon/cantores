<?php

use App\Models\Folder;
use App\Models\Loan;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\ScoreFile;
use App\Models\ScorePublication;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\ScoreFileStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Closing an account has to reach everything the person could have deleted by
 * hand — the kottatár and the lending links included.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->accounts = app(AccountDeletionService::class);
});

/**
 * A score with real bytes on the private disk, so the artifacts can be checked.
 */
function scoreWithFile(User $user): ScoreFile
{
    $score = Score::factory()->linksOnly()->create(['user_id' => $user->id]);
    $scoreFile = ScoreFile::factory()->create(['score_id' => $score->id]);

    app(ScoreFileStorage::class)->put($scoreFile->path, 'kotta bytes');

    return $scoreFile;
}

it('deletes the scores and their encrypted files', function () {
    $user = User::factory()->create();
    $scoreFile = scoreWithFile($user);

    $this->accounts->delete($user);

    expect(Score::query()->find($scoreFile->score_id))->toBeNull()
        ->and(ScoreFile::query()->find($scoreFile->id))->toBeNull()
        ->and(Storage::disk('private')->exists($scoreFile->path))->toBeFalse();
});

it('deletes the folders and the lending links handed out', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $plan = MusicPlan::factory()->create(['user_id' => $user->id]);

    $scoreLoan = Loan::factory()->of($score)->create();
    $folderLoan = Loan::factory()->of($folder)->create();
    $planLoan = Loan::factory()->of($plan)->create();

    $this->accounts->delete($user);

    expect(Folder::query()->find($folder->id))->toBeNull()
        ->and(Loan::query()->find($scoreLoan->id))->toBeNull()
        ->and(Loan::query()->find($folderLoan->id))->toBeNull()
        ->and(Loan::query()->find($planLoan->id))->toBeNull();
});

it('deletes a lending link whose target was already gone', function () {
    $user = User::factory()->create();
    $orphan = Loan::factory()->create([
        'user_id' => $user->id,
        'lendable_type' => Score::class,
        'lendable_id' => 99999,
    ]);

    $this->accounts->delete($user);

    expect(Loan::query()->find($orphan->id))->toBeNull();
});

it('deletes the borrowing history on both sides', function () {
    $lender = User::factory()->create();
    $borrower = User::factory()->create();

    $lentScore = Score::factory()->create(['user_id' => $lender->id]);
    $borrowerLoan = Loan::factory()->of($lentScore)->create(['user_id' => $lender->id]);

    // What the borrower kept from somebody else's link.
    $kept = ReceivedLoan::factory()->kept()->create([
        'user_id' => $borrower->id,
        'loan_id' => $borrowerLoan->id,
    ]);

    // What somebody else kept from the borrower's own link.
    $ownScore = Score::factory()->create(['user_id' => $borrower->id]);
    $ownLoan = Loan::factory()->of($ownScore)->create();
    $receiptOfOwnLoan = ReceivedLoan::factory()->create([
        'user_id' => $lender->id,
        'loan_id' => $ownLoan->id,
    ]);

    $this->accounts->delete($borrower);

    expect(ReceivedLoan::query()->find($kept->id))->toBeNull()
        ->and(ReceivedLoan::query()->find($receiptOfOwnLoan->id))->toBeNull()
        ->and(Loan::query()->find($borrowerLoan->id))->not->toBeNull();
});

it('takes the published scores out of the public library', function () {
    $user = User::factory()->create();
    $score = Score::factory()->create(['user_id' => $user->id]);
    ScorePublication::factory()->of($score)->approved()->create();

    $this->accounts->delete($user);

    expect(Score::query()->find($score->id))->toBeNull()
        ->and(ScorePublication::query()->where('score_id', $score->id)->exists())->toBeFalse();
});

it('leaves other people and the shared database alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $otherFile = scoreWithFile($other);
    $otherLoan = Loan::factory()->of(Score::query()->find($otherFile->score_id))->create();
    $publicMusic = Music::factory()->create(['user_id' => $user->id, 'is_private' => false]);

    scoreWithFile($user);

    $this->accounts->delete($user);

    expect(Score::query()->find($otherFile->score_id))->not->toBeNull()
        ->and(Storage::disk('private')->exists($otherFile->path))->toBeTrue()
        ->and(Loan::query()->find($otherLoan->id))->not->toBeNull()
        ->and(Music::query()->find($publicMusic->id))->not->toBeNull();
});

it('anonymizes the account instead of removing the row', function () {
    $user = User::factory()->create();

    $this->accounts->delete($user);

    $user->refresh();

    expect($user->name)->toBe('Deleted User')
        ->and($user->email)->toBe('deleted-'.$user->id.'@example.com')
        ->and($user->blocked)->toBeTrue()
        ->and($user->city_id)->not->toBeNull()
        ->and($user->first_name_id)->not->toBeNull();
});

it('deletes a lendable\'s loans when it is deleted on its own', function () {
    $folder = Folder::factory()->create();
    $loan = Loan::factory()->of($folder)->create();

    $folder->delete();

    expect(Loan::query()->find($loan->id))->toBeNull();
});

it('reaches the kottatár when a user closes their own account', function () {
    $user = User::factory()->create();
    $scoreFile = scoreWithFile($user);
    $loan = Loan::factory()->of(Score::query()->find($scoreFile->score_id))->create();

    actingAs($user);

    Livewire::test('pages::settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(Score::query()->find($scoreFile->score_id))->toBeNull()
        ->and(Loan::query()->find($loan->id))->toBeNull()
        ->and(Storage::disk('private')->exists($scoreFile->path))->toBeFalse();
});

it('reaches the kottatár when an admin deletes an account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $scoreFile = scoreWithFile($user);
    $loan = Loan::factory()->of(Score::query()->find($scoreFile->score_id))->create();

    actingAs($admin);

    Livewire::test('pages::admin.users')->call('deleteUser', $user->id);

    expect(Score::query()->find($scoreFile->score_id))->toBeNull()
        ->and(Loan::query()->find($loan->id))->toBeNull()
        ->and($user->fresh()->name)->toBe('Deleted User');
});
