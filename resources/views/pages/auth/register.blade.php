<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <p class="text-xs text-gray-500 mt-1">
                {{ __("This name will not be shown on the site, unless you want to publish it for other registered users. You don't have to tell your real name.") }}
            </p>


                        <!-- Nickname Group: City + First Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nickname') }}</label>
                <div class="flex gap-2">
                    <flux:select
                        name="city_id"
                        :label="null"
                        required
                        class="flex-1"
                        :value="old('city_id', $selectedCityId ?? null)"
                    >
                        <option value="">{{ __('Select a city') }}</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}" @selected(old('city_id', $selectedCityId ?? null) == $city->id)>
                                {{ $city->name }}
                            </option>
                        @endforeach
                    </flux:select>
                    <flux:select
                        name="first_name_id"
                        :label="null"
                        required
                        class="flex-1"
                        :value="old('first_name_id', $selectedFirstNameId ?? null)"
                    >
                        <option value="">{{ __('Select a first name') }}</option>
                        @foreach ($firstNames as $firstName)
                            <option value="{{ $firstName->id }}" @selected(old('first_name_id', $selectedFirstNameId ?? null) == $firstName->id)>
                                {{ $firstName->name }}
                            </option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

                        <p class="text-xs text-gray-500 mt-1">
                {{ __("The nickname is used throughout the site to identify the work you shared with others. By default you don't have to share anything, and you can keep everything private.") }}
            </p>



            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />




            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
