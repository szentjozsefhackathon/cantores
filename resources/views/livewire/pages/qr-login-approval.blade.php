<div class="flex flex-col gap-6">
    @if ($invalid)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
            <flux:icon.exclamation-triangle class="size-8 text-zinc-400" />
            <flux:text data-test="qr-invalid">{{ __('This code is no longer valid.') }}</flux:text>
            <flux:button :href="route('plan-documents')" wire:navigate variant="ghost">
                {{ __('Booklets & Projections') }}
            </flux:button>
        </div>
    @elseif ($signedOut)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
            <flux:icon.check-circle class="size-8 text-green-500" />
            <flux:text data-test="qr-signed-out">{{ __('The device has been logged out.') }}</flux:text>
            <flux:button :href="route('plan-documents')" wire:navigate variant="ghost">
                {{ __('Booklets & Projections') }}
            </flux:button>
        </div>
    @elseif ($approved)
        <div class="flex flex-col items-center gap-4 rounded-xl border border-zinc-200 p-8 text-center dark:border-zinc-700">
            <flux:icon.check-circle class="size-8 text-green-500" />
            <flux:text data-test="qr-approved">{{ __('The device is signed in.') }}</flux:text>
            <flux:text class="text-sm text-zinc-500">
                {{ __('It stays signed in until you close its browser or log it out from here.') }}
            </flux:text>

            <div class="flex flex-col gap-2">
                <flux:button wire:click="reject" variant="danger" icon="power" data-test="qr-sign-out">
                    {{ __('Log out this device') }}
                </flux:button>
                <flux:link :href="route('devices.edit')" wire:navigate class="text-sm">
                    {{ __('Signed-in devices') }}
                </flux:link>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-5 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="text-center">
                <flux:heading size="lg">{{ __('Sign this device in?') }}</flux:heading>
                <flux:subheading>{{ $device }}@if ($ipAddress) · {{ $ipAddress }}@endif</flux:subheading>
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

            <flux:callout variant="warning" icon="shield-exclamation">
                {{ __('If that code is not on a screen in front of you, do not approve this.') }}
            </flux:callout>

            <div class="flex flex-col gap-2">
                <flux:button wire:click="approve" variant="primary" icon="check" data-test="qr-approve">
                    {{ __('Approve') }}
                </flux:button>
                <flux:button wire:click="reject" variant="ghost" data-test="qr-reject">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
