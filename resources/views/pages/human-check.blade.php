<x-layouts::app.main :title="__('Human check')" :noindex="true">
    <x-turnstile.scripts />

    <main class="mx-auto flex w-full max-w-lg flex-col items-center gap-6 py-12 text-center">
        <flux:icon name="hand-raised" class="size-10 text-accent" />

        <div class="space-y-2">
            <flux:heading size="xl">{{ __('Just checking you are a person') }}</flux:heading>
            <flux:text>
                {{ __('Scores are lent to people, not to robots. This takes a moment and you will only be asked once.') }}
            </flux:text>
        </div>

        <form method="POST" action="{{ route('human-check.store') }}" id="human-check-form" class="flex flex-col items-center gap-4">
            @csrf

            <x-turnstile data-callback="humanCheckPassed" />

            @error('cf-turnstile-response')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <flux:button variant="primary" type="submit" icon-trailing="arrow-right">
                {{ __('Continue') }}
            </flux:button>
        </form>

        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
            {{ __('Signed-in members are never asked.') }}
            <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
        </flux:text>
    </main>

    <script>
        window.humanCheckPassed = function () {
            document.getElementById('human-check-form').submit();
        };
    </script>
</x-layouts::app.main>
