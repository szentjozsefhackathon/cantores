<div wire:init="startPairing" class="flex flex-col gap-6">
    <div class="text-center">
        <flux:heading size="lg">{{ __('Sign in with a QR code') }}</flux:heading>
        <flux:subheading>{{ __('Scan this code with your phone') }}</flux:subheading>
    </div>

    @if ($abandoned)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-zinc-200 p-8 dark:border-zinc-700">
            <flux:text class="text-center">{{ __('This code is no longer valid.') }}</flux:text>
            <flux:button wire:click="restart" variant="primary" icon="arrow-path" data-test="qr-restart">
                {{ __('Show a new code') }}
            </flux:button>
        </div>
    @elseif ($token === null)
        {{-- Nothing is minted until Livewire boots, so a crawler reads this and writes nothing. --}}
        <div class="flex items-center justify-center gap-2 rounded-xl border border-zinc-200 p-8 dark:border-zinc-700">
            <flux:icon.loading class="size-4 shrink-0 text-zinc-400" />
            <flux:text class="text-sm text-zinc-500">{{ __('Preparing a code…') }}</flux:text>
        </div>
    @else
        <div class="flex flex-col items-center gap-5">
            {{-- The engraved SVG carries its own width and height, so the
                 container overrides them: this is read by a camera across a
                 room on one screen and held at arm's length on another. --}}
            <div class="rounded-xl bg-white p-4 shadow-sm" data-test="qr-code">
                <div class="w-64 max-w-full [&>svg]:h-auto [&>svg]:w-full">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            <div class="text-center">
                <flux:text class="text-sm text-zinc-500">
                    {{ __('Check that this code is on the other screen:') }}
                </flux:text>
                <div
                    class="mt-1 font-mono text-3xl font-semibold tracking-[0.3em] text-zinc-900 dark:text-white"
                    data-test="qr-confirmation-code"
                >{{ $confirmationCode }}</div>
            </div>
        </div>

        {{-- The phone answers on its own request; poll until it does rather than
             asking whoever is standing at the laptop to keep reloading. --}}
        <div wire:poll.2s="checkPairing" class="flex items-center justify-center gap-2">
            <flux:icon.loading class="size-4 shrink-0 text-zinc-400" />
            <flux:text class="text-sm text-zinc-500">
                {{ $scanned
                    ? __('Waiting for confirmation on your phone…')
                    : __('Waiting for the code to be scanned…') }}
            </flux:text>
        </div>
    @endif

    <flux:separator variant="subtle" />

    <div class="text-center">
        <flux:link :href="route('login')" wire:navigate class="text-sm">
            {{ __('Log in with an email address instead') }}
        </flux:link>
    </div>
</div>
