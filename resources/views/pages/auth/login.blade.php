<x-layouts::auth :logo="false">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" inline />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if (Route::has('register'))
            <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-indigo-600 to-purple-700 p-6 shadow-sm dark:from-indigo-800 dark:to-purple-900">
                <div class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 pointer-events-none"></div>
                <div class="relative">
                    <flux:heading size="lg" class="text-white!">{{ __('New here?') }}</flux:heading>
                    <p class="mt-1 text-sm text-indigo-100">
                        {{ __('Create a free account to plan your liturgies, build booklets and keep your scores in one place.') }}
                    </p>
                    <flux:button
                        :href="route('register')"
                        wire:navigate
                        icon="user-plus"
                        class="mt-4 w-full bg-white! text-indigo-700! hover:bg-indigo-50! font-semibold"
                        data-test="register-link"
                    >
                        {{ __('Create an account') }}
                    </flux:button>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <!-- Cloudflare Turnstile -->
            <x-turnstile />
            @error('cf-turnstile-response')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth>
