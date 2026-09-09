<x-layouts::auth :logo="false">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" inline />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <flux:field>
                <flux:label>{{ __('Full name') }}</flux:label>
                <flux:description>{{ __('This name will not be shown on the site, unless you want to publish it for other registered users. You don\'t have to tell your real name.') }}</flux:description>
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
            <flux:field>
                <flux:label>{{ __('Nickname') }}</flux:label>
                <livewire:auth.nickname-picker
                    :city-id="old('city_id', $selectedCityId)"
                    :first-name-id="old('first_name_id', $selectedFirstNameId)" />
            </flux:field>

            <!-- Email Address -->
            <flux:field>
                <flux:label>{{ __('Email address') }}</flux:label>
                <flux:description>
                    {{ __("Az email-címedet soha nem osztjuk meg másokkal, nem jelenítjük meg az oldalon. Csak te láthatod, és jelszóemlékeztető és egyéb biztonsági értesítésekhez használjuk.") }}
                </flux:description>

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
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:description>{{ __('Legalább 8 karakter hosszú jelszót adj meg.') }}</flux:description>
                <flux:input
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    viewable />
                <flux:error name="password" />
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

            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Fiók létrehozásával elfogadod az') }}
                <flux:link :href="route('terms')" wire:navigate>{{ __('Általános Szerződési Feltételeket') }}</flux:link>
                {{ __('és az') }}
                <flux:link :href="route('privacy')" wire:navigate>{{ __('Adatvédelmi Tájékoztatót') }}</flux:link>.
            </p>

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