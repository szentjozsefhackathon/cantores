<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Celebration;
use App\Models\Collection;
use App\Models\Folder;
use App\Models\Loan;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Models\ReceivedLoan;
use App\Models\Score;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Closes an account: removes what belonged to the person, keeps only what the
 * community's shared data depends on.
 *
 * The user row itself survives, anonymized, because published master data —
 * musics, collections, authors — is referenced by other people's music plans and
 * would take them down with it. Everything the person could have deleted by hand
 * goes: plans, scores, uploaded files, folders, and every lending link they
 * handed out. Closing an account must never leave more behind than emptying it
 * one screen at a time would have.
 *
 * Scores are deleted one at a time on purpose. `Score::deleting` is what removes
 * the encrypted bytes from the private disk, and a single mass delete would drop
 * the rows while leaving the artifacts on the volume forever.
 */
class AccountDeletionService
{
    public function __construct(private readonly NicknameService $nicknames) {}

    /**
     * Delete the user's own content and anonymize what is left of the account.
     */
    public function delete(User $user): void
    {
        // Resolved before anything is destroyed: it reads `users`, and it is the
        // one step here that can come back empty.
        $nickname = $this->nicknames->randomPairExcluding($user->getKey())
            ?? [$user->city_id, $user->first_name_id];

        DB::transaction(function () use ($user, $nickname): void {
            $this->deletePlans($user);
            $this->deleteScoresAndFolders($user);
            $this->deleteLoans($user);
            $this->deletePrivateMasterData($user);

            $user->syncRoles([]);

            $this->anonymize($user, $nickname);
        });
    }

    /**
     * The user's music plans, and the custom slots that only they used.
     *
     * The database cascade removes slot plans and assignments.
     */
    private function deletePlans(User $user): void
    {
        MusicPlan::query()->where('user_id', $user->getKey())
            ->each(fn (MusicPlan $plan) => $plan->delete());

        MusicPlanSlot::query()->where('user_id', $user->getKey())->where('is_custom', true)
            ->each(fn (MusicPlanSlot $slot) => $slot->forceDelete());
    }

    /**
     * The user's kottatár: every score, its files, and the folders holding them.
     *
     * Published scores go too. Their owner could have deleted them from the score
     * list at any time, so an account closure has to be able to as well; the audit
     * trail of the publication decisions outlives the rows either way.
     */
    private function deleteScoresAndFolders(User $user): void
    {
        Folder::query()->where('user_id', $user->getKey())
            ->each(fn (Folder $folder) => $folder->delete());

        Score::query()->where('user_id', $user->getKey())
            ->each(fn (Score $score) => $score->delete());
    }

    /**
     * Both sides of lending: the links this user handed out, and the record of
     * what they opened or kept from other people's links.
     *
     * Deleting the lendables above already took their loans with them, so what is
     * left here are links whose target was gone before today. The receipts of the
     * user's own loans follow those loans by cascade.
     */
    private function deleteLoans(User $user): void
    {
        Loan::query()->where('user_id', $user->getKey())->delete();

        ReceivedLoan::query()->where('user_id', $user->getKey())->delete();
    }

    /**
     * Master data the user created but never published. Public rows stay: other
     * people's plans point at them.
     */
    private function deletePrivateMasterData(User $user): void
    {
        Author::query()->where('user_id', $user->getKey())->where('is_private', true)
            ->each(fn (Author $author) => $author->delete());

        Collection::query()->where('user_id', $user->getKey())->where('is_private', true)
            ->each(fn (Collection $collection) => $collection->delete());

        Music::query()->where('user_id', $user->getKey())->where('is_private', true)
            ->each(fn (Music $music) => $music->delete());

        Celebration::query()->where('user_id', $user->getKey())->where('is_custom', true)->delete();
    }

    /**
     * Strip the account down to a locked, unidentifiable shell.
     *
     * @param  array{0: int|null, 1: int|null}  $nickname
     */
    private function anonymize(User $user, array $nickname): void
    {
        $user->forceFill([
            'name' => 'Deleted User',
            'email' => 'deleted-'.$user->getKey().'@example.com',
            'password' => Hash::make(Str::random(32)),
            'city_id' => $nickname[0],
            'first_name_id' => $nickname[1],
            'current_genre_id' => null,
            'blocked' => true,
            'blocked_at' => now(),
            'email_verified_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
        ])->save();
    }
}
