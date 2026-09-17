{{-- Naming a screen, and saying one is not a screen at all.

     The button is a pencil and nothing else, because this is a thing done once
     per device and then never again: on the phone it sits beside the screen's
     name, and on the presenter it sits under the sentence saying the screen is
     waiting, which is where somebody is standing at the laptop with nothing else
     to do. --}}
<div class="flex min-w-0 items-center gap-2">
    @if($showLabel)
        {{-- Said by this component and not by the page around it, so that a name
             just typed is on the screen the moment it is saved. --}}
        <span @class(['min-w-0 truncate text-sm', 'text-white/50' => $onBlack, 'text-zinc-500' => ! $onBlack])>
            {{ $screen->label() }}
        </span>
    @endif

    <flux:tooltip :content="__('Name this screen')">
        <flux:button
            size="sm"
            variant="ghost"
            icon="pencil-square"
            wire:click="openSettings"
            :aria-label="__('Name this screen')"
            @class(['!text-white/70' => $onBlack])
        />
    </flux:tooltip>

    <flux:modal wire:model="open" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('This screen') }}</flux:heading>
                <flux:subheading>
                    {{ __('What you call this device, and whether it is listed as a screen. Both are yours alone and stay with the device.') }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>

                <flux:input
                    wire:model="name"
                    maxlength="{{ \App\Models\DeviceName::MAX_LENGTH }}"
                    :placeholder="$screen->describeDevice()"
                    autocomplete="off"
                />

                <flux:description>
                    {{ __('Left empty, it is listed as :device.', ['device' => $screen->describeDevice()]) }}
                </flux:description>

                <flux:error name="name" />
            </flux:field>

            {{-- The answer no heuristic gets right. A laptop at home is the same
                 account, the same device string and genuinely live; the person
                 who owns both is the only one who knows which room it is in. --}}
            <flux:field variant="inline">
                <flux:switch wire:model="offered" />
                <flux:label>{{ __('Offer this device as a projection screen') }}</flux:label>
                <flux:description>
                    {{ __('Turn this off on a computer that is never the one the room reads from. It can still present in its own window.') }}
                </flux:description>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
