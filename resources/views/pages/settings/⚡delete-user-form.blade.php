<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\Author;
use App\Models\Celebration;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicPlan;
use App\Models\MusicPlanSlot;
use App\Services\NicknameService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user with anonymization.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user) {
            // Hard delete all music plans (DB cascade removes slot plans and assignments)
            MusicPlan::where('user_id', $user->id)->each(fn ($plan) => $plan->delete());

            // Hard delete any remaining custom slots that belonged to this user
            MusicPlanSlot::where('user_id', $user->id)->where('is_custom', true)->each(fn ($slot) => $slot->forceDelete());

            // Hard delete private authors (DB cascade removes author_music pivot entries)
            Author::where('user_id', $user->id)->where('is_private', true)->each(fn ($author) => $author->delete());

            // Hard delete private collections (DB cascade removes music_collection pivot entries)
            Collection::where('user_id', $user->id)->where('is_private', true)->each(fn ($collection) => $collection->delete());

            // Hard delete private musics (DB cascade removes related pivot entries)
            Music::where('user_id', $user->id)->where('is_private', true)->each(fn ($music) => $music->delete());

            // Delete custom celebrations owned by this user
            Celebration::where('user_id', $user->id)->where('is_custom', true)->delete();

            // Remove all roles from the user
            $user->syncRoles([]);

            // Find an unused city+firstname pair for anonymization
            [$cityId, $firstNameId] = app(NicknameService::class)->randomPairExcluding($user->id);

            // Anonymize the user record
            $user->forceFill([
                'name' => 'Deleted User',
                'email' => 'deleted-'.$user->id.'@example.com',
                'password' => Hash::make(Str::random(32)),
                'city_id' => $cityId,
                'first_name_id' => $firstNameId,
                'current_genre_id' => null,
                'blocked' => true,
                'blocked_at' => now(),
                'email_verified_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ])->save();
        });

        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('This will permanently delete all your music plans, private authors, private collections, and private music. Your account will be anonymized and you will be logged out. This action cannot be undone. Please enter your password to confirm.') }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:input wire:model="password" type="password" />
                <flux:error name="password" />
            </flux:field>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit" data-test="confirm-delete-user-button">
                    {{ __('Delete account') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
