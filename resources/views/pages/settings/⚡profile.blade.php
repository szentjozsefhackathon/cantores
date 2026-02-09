<?php

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public ?int $cityId = null;
    public ?int $firstNameId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->cityId = $user->city_id;
        $this->firstNameId = $user->first_name_id;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $rules = [
            ...$this->profileRules($user->id),
            'cityId' => ['required', 'integer', 'exists:cities,id'],
            'firstNameId' => ['required', 'integer', 'exists:first_names,id'],
        ];

        $validated = $this->validate($rules, [], [
            'cityId' => __('city'),
            'firstNameId' => __('first name'),
        ]);

        // Check for duplicate combination (excluding current user)
        $exists = User::where('city_id', $validated['cityId'])
            ->where('first_name_id', $validated['firstNameId'])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            $this->addError('cityId', __('This city and first name combination is already taken.'));
            $this->addError('firstNameId', __('This city and first name combination is already taken.'));
            return;
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'city_id' => $validated['cityId'],
            'first_name_id' => $validated['firstNameId'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Generate a random city/first name pair that is not already used by another user.
     */
    public function randomizeNickname(): void
    {
        $cities = \App\Models\City::orderBy('name')->get();
        $firstNames = \App\Models\FirstName::orderBy('name')->get();
        $currentUser = Auth::user();

        // Get used combinations excluding the current user's combination
        $usedCombinations = \App\Models\User::where('id', '!=', $currentUser->id)
            ->select('city_id', 'first_name_id')
            ->get()
            ->map(fn ($user) => $user->city_id . '_' . $user->first_name_id)
            ->toArray();

        $availableCombinations = [];

        foreach ($cities as $city) {
            foreach ($firstNames as $firstName) {
                $key = $city->id . '_' . $firstName->id;
                if (!in_array($key, $usedCombinations)) {
                    $availableCombinations[] = ['city_id' => $city->id, 'first_name_id' => $firstName->id];
                }
            }
        }

        if (!empty($availableCombinations)) {
            $random = $availableCombinations[array_rand($availableCombinations)];
            $this->cityId = $random['city_id'];
            $this->firstNameId = $random['first_name_id'];
        } else {
            // If all combinations are used (excluding current user), fallback to random city and first name
            $this->cityId = $cities->isNotEmpty() ? $cities->random()->id : null;
            $this->firstNameId = $firstNames->isNotEmpty() ? $firstNames->random()->id : null;
        }
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    #[Computed]
    public function cities(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\City::orderBy('name')->get();
    }

    #[Computed]
    public function firstNames(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\FirstName::orderBy('name')->get();
    }

    #[Computed]
    public function displayName(): string
    {
        $user = Auth::user();
        return $user->display_name;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" >
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">

            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:text class="font-medium">{{ __('Display Name') }}</flux:text>
                <flux:text class="mt-1">{{ $this->displayName }}</flux:text>
            </div>

            <div>
                <flux:text class="font-medium mb-1">{{ __('Nickname') }}</flux:text>
                <div class="flex gap-2">
                    <flux:select
                        wire:model="cityId"
                        :label="null"
                        required
                        class="flex-1"
                    >
                        <option value="">{{ __('Select a city') }}</option>
                        @foreach ($this->cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:select
                        wire:model="firstNameId"
                        :label="null"
                        required
                        class="flex-1"
                    >
                        <option value="">{{ __('Select a first name') }}</option>
                        @foreach ($this->firstNames as $firstName)
                            <option value="{{ $firstName->id }}">{{ $firstName->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:button type="button" variant="ghost" wire:click="randomizeNickname" class="whitespace-nowrap">
                        {{ __('Random nickname') }}
                    </flux:button>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __("Your display name is composed of your selected first name and city.") }}
                </p>
            </div>

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
