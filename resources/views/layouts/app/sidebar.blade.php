<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <!-- Realm Selector -->
        <div>
            <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                Műfaj
            </flux:text>
            <div class="border-b border-zinc-200 dark:border-zinc-700">
                <livewire:realm-selector />
            </div>
        </div>

        <flux:sidebar.nav>
            <flux:sidebar.group class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="folder" :href="route('collections')" :current="request()->routeIs('collections')" wire:navigate>
                    {{ __('Collections') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="music" :href="route('musics')" :current="request()->routeIs('musics')" wire:navigate>
                    {{ __('Music Pieces') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="globe" :href="route('music-plans')" :current="request()->routeIs('music-plans')" wire:navigate>
                    Közzétett énekrendek
                </flux:sidebar.item>
                <flux:sidebar.item icon="calendar-days" :href="route('my-music-plans')" :current="request()->routeIs('my-music-plans')" wire:navigate>
                    Énekrendjeim
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>

            @if(auth()->check() && auth()->user()->is_admin)
            <flux:sidebar.item icon="shield-check" :href="route('admin.music-plan-templates')" :current="request()->routeIs('admin.nickname-data')" wire:navigate>
                {{ __('Admin') }}
            </flux:sidebar.item>
            @endif
        </flux:sidebar.nav>

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->displayName" />
    </flux:sidebar>


    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

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
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>