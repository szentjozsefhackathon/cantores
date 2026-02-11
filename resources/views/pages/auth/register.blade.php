<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-2">
            @csrf

            <!-- Name -->
            <flux:field>
                <flux:label>{{ __('Full name') }}</flux:label>
                <flux:text size="sm">{{__('This name will not be shown on the site, unless you want to publish it for other registered users. You don\'t have to tell your real name.') }}</flux:text>
                <flux:input
                    name="name"
                    :value="old('name')"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('Full name')" />
            </flux:field>

            <!-- Nickname Group: City + First Name -->
            <div x-data="{
                cityId: {{ json_encode(old('city_id', $selectedCityId)) }},
                firstNameId: {{ json_encode(old('first_name_id', $selectedFirstNameId)) }},
                async randomize() {
                    try {
                        const response = await fetch('{{ route('random-nickname') }}');
                        const data = await response.json();
                        this.cityId = data.city_id;
                        this.firstNameId = data.first_name_id;
                    } catch (error) {
                        console.error('Failed to fetch random nickname', error);
                    }
                }
            }">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Nickname') }}</label>
                <flux:text class="text-xs">
                    {{ __("The nickname is used throughout the site to identify the work you shared with others. By default you don't have to share anything, and you can keep everything private.") }}
                </flux:text>
                <div class="flex gap-2 mt-1">
                    <flux:select
                        name="city_id"
                        :label="null"
                        required
                        class="flex-1"
                        x-model="cityId">
                        <option value="">{{ __('Select a city') }}</option>
                        @foreach ($cities as $city)
                        <option value="{{ $city->id }}">
                            {{ $city->name }}
                        </option>
                        @endforeach
                    </flux:select>
                    <flux:select
                        name="first_name_id"
                        :label="null"
                        required
                        class="flex-1"
                        x-model="firstNameId">
                        <option value="">{{ __('Select a first name') }}</option>
                        @foreach ($firstNames as $firstName)
                        <option value="{{ $firstName->id }}">
                            {{ $firstName->name }}
                        </option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="flex justify-center mt-2">
                    <flux:button icon="dices" type="button" @click="randomize" class="whitespace-nowrap" variant="filled">
                        {{ __('Random nickname') }}
                    </flux:button>
                </div>


                <div class="flex flex-col gap-2 mt-1">
                <!-- Email Address -->
                <flux:field>
                    <flux:label>{{ __('Email address') }}</flux:label>
                    <flux:text size="sm">
                        {{ __("Az email-címedet soha nem osztjuk meg másokkal, nem jelenítjük meg az oldalon. Csak te láthatod, és jelszóemlékeztető és egyéb biztonsági értesítésekhez használjuk.") }}
                    </flux:text>

                    <flux:input
                        name="email"
                        :value="old('email')"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="email@example.com" />
                </flux:field>


                <!-- Password -->
                <flux:field>
                    <flux:input
                        name="password"
                        :label="__('Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('Password')"
                        viewable />
                </flux:field>
                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    viewable />

                <!-- Cloudflare Turnstile -->
                <x-turnstile />
                @error('cf-turnstile-response')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                </div>
                <div class="flex items-center justify-end">
                    <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                        {{ __('Create account') }}
                    </flux:button>
                </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400 mt-2">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>