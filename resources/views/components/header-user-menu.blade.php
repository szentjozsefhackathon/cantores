{{-- The signed in visitor's own menu, for a top bar: who they are, their
     settings, and the way out. --}}
<flux:dropdown position="top" align="end">
    <flux:profile
        :initials="auth()->user()->initials()"
        icon-trailing="chevron-down" />

    <flux:menu>
        <flux:menu.radio.group>
            <div class="p-0 text-sm font-normal">
                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                    <flux:avatar
                        :name="auth()->user()->displayName"
                        :initials="auth()->user()->initials()" />

                    <div class="grid flex-1 text-start text-sm leading-tight">
                        <flux:heading class="truncate">{{ auth()->user()->displayName }}</flux:heading>
                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                    </div>
                </div>
            </div>
        </flux:menu.radio.group>

        <flux:menu.separator />

        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
        </flux:menu.radio.group>

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer"
                data-test="logout-button">
                {{ __('Log Out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
