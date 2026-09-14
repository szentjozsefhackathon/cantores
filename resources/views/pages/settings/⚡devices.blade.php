<?php

use App\Models\DevicePairing;
use App\Services\SessionStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * The screens this account is signed in on by QR code.
     *
     * Not every session — only the borrowed ones, which are the only ones a
     * person is likely to have walked away from.
     *
     * @return Collection<int, DevicePairing>
     */
    #[Computed]
    public function devices(): Collection
    {
        return Auth::user()
            ->devicePairings()
            ->liveDevices()
            ->latest('claimed_at')
            ->get();
    }

    /**
     * The pairing this very browser is signed in on, if it is one of them.
     */
    #[Computed]
    public function currentPairingId(): ?int
    {
        return session()->get(DevicePairing::DEVICE_SESSION_KEY);
    }

    /**
     * End a device's session from here.
     *
     * Deleting the row is the immediate half; EnforcePairedDeviceSession is the
     * half that works whatever the session driver is.
     */
    public function signOut(int $pairingId, SessionStore $sessions): void
    {
        $pairing = Auth::user()->devicePairings()->liveDevices()->find($pairingId);

        if ($pairing === null) {
            return;
        }

        $sessions->forget($pairing->session_id);
        $pairing->revoke();

        unset($this->devices);

        $this->dispatch('toast', message: __('The device has been logged out.'), type: 'success');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Signed-in devices') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Signed-in devices')" :subheading="__('Screens you signed in with a QR code. Log one out if you left it behind.')">
        <div class="flex flex-col gap-4">
            @forelse ($this->devices as $device)
                <div
                    class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"
                    data-test="paired-device"
                    wire:key="device-{{ $device->id }}"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <flux:text class="font-medium">{{ $device->describeDevice() }}</flux:text>
                            @if ($device->id === $this->currentPairingId)
                                <flux:badge size="sm" color="green">{{ __('This device') }}</flux:badge>
                            @endif
                        </div>

                        <flux:text class="mt-1 text-sm text-zinc-500">
                            @if ($device->ip_address)
                                {{ $device->ip_address }} ·
                            @endif
                            {{ __('Signed in :time', ['time' => $device->claimed_at?->diffForHumans()]) }}
                        </flux:text>

                        @if ($device->last_seen_at)
                            <flux:text class="text-sm text-zinc-500">
                                {{ __('Last active :time', ['time' => $device->last_seen_at->diffForHumans()]) }}
                            </flux:text>
                        @endif
                    </div>

                    <flux:button
                        wire:click="signOut({{ $device->id }})"
                        variant="danger"
                        size="sm"
                        icon="power"
                        data-test="sign-out-device"
                    >
                        {{ __('Log out') }}
                    </flux:button>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500" data-test="no-paired-devices">
                    {{ __('No devices are signed in with a QR code.') }}
                </flux:text>
            @endforelse

            <flux:separator variant="subtle" />

            <flux:text class="text-sm text-zinc-500">
                {{ __('To sign a new screen in, open :url on it and scan the code.', ['url' => route('qr-login')]) }}
            </flux:text>
        </div>
    </x-pages::settings.layout>
</section>
