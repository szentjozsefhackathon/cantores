<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" x-data="{
  init() {
    const root = document.documentElement;
    const apply = () => root.setAttribute('data-theme', root.classList.contains('dark') ? 'dark' : 'light');
    apply();
    new MutationObserver(() => apply()).observe(root, { attributes: true, attributeFilter: ['class'] });
  }
}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
            <!-- Dark Mode Switcher -->
            <div x-data="{ cycle() { const s = ['light','dark','system']; $flux.appearance = s[(s.indexOf($flux.appearance) + 1) % 3]; } }">
                <flux:button variant="ghost" square @click="cycle()" aria-label="Toggle appearance">
                    <flux:icon x-show="$flux.appearance === 'light'" name="sun" variant="mini" />
                    <flux:icon x-show="$flux.appearance === 'dark'" name="moon" variant="mini" />
                    <flux:icon x-show="$flux.appearance === 'system'" name="computer-desktop" variant="mini" />
                </flux:button>
            </div>
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <!-- Genre Selector -->
        <div>
            <div class="flex items-center gap-1">
                <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                    Műfaj
                </flux:text>
                <flux:dropdown position="right" align="start">
                    <flux:button variant="ghost" square class="size-5! text-neutral-400 dark:text-neutral-500" aria-label="Műfajok leírása">
                        <flux:icon name="information-circle" variant="mini" class="size-4!" />
                    </flux:button>
                    <flux:menu class="w-56 p-3">
                        <flux:text class="mb-2 text-xs font-semibold">Műfaj ikonok</flux:text>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <flux:icon name="organist" class="size-4 shrink-0 text-blue-500" />
                                <flux:text class="text-xs">{{ __('Organist') }} - Gregorián, népénekes stb.</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon name="guitar" class="size-4 shrink-0 text-green-500" />
                                <flux:text class="text-xs">{{ __('Guitarist') }} - Gitáros, könnyűzenei stb.</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:icon name="landmark" variant="mini" class="size-4 shrink-0 text-neutral-500 dark:text-neutral-400" />
                                <flux:text class="text-xs">{{ __('Other') }} - Egyéb, szimfonikus zenekar stb.</flux:text>
                            </div>
                        </div>
                    </flux:menu>
                </flux:dropdown>
            </div>
            <div>
                <livewire:genre-selector />
            </div>
        </div>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="circle-stack" :href="route('music-database')" :current="request()->routeIs('music-database')" wire:navigate>
                Énektár
            </flux:sidebar.item>
            <flux:sidebar.group heading="Énekrend">
                <flux:sidebar.item icon="list-music" :href="route('my-music-plans')" :current="request()->routeIs('my-music-plans')" wire:navigate>
                    Énekrendjeim
                </flux:sidebar.item>
                <flux:sidebar.item icon="globe" :href="route('music-plans')" :current="request()->routeIs('music-plans')" wire:navigate>
                    Közzétett énekrendek
                </flux:sidebar.item>
            </flux:sidebar.group>
            <flux:sidebar.group heading="Könyvtár">
                <flux:sidebar.item icon="music" :href="route('musics')" :current="request()->routeIs('musics')" wire:navigate>
                    {{ __('Music Pieces') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="book-open" :href="route('collections')" :current="request()->routeIs('collections')" wire:navigate>
                    {{ __('Collections') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="users" :href="route('authors')" :current="request()->routeIs('authors', 'authors-editor')" wire:navigate>
                    {{ __('Authors') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
            @if(auth()->check() && auth()->user()->isEditor)
            <flux:sidebar.group heading="Szerkesztő">
            <flux:sidebar.item icon="combine" :href="route('music-merger')" :current="request()->routeIs('music-merge')" wire:navigate>
                Énekek egyesítése
            </flux:sidebar.item>
            <flux:sidebar.item icon="copy" :href="route('duplicate-merger')" :current="request()->routeIs('duplicate-merger')" wire:navigate>
                Duplikátumok egyesítése
            </flux:sidebar.item>
            <flux:sidebar.item icon="check-circle" :href="route('music-verifier')" :current="request()->routeIs('music-verifier')" wire:navigate>
                Énekek ellenőrzése
            </flux:sidebar.item>
            <flux:sidebar.item icon="tag" :href="route('music-tag-manager')" :current="request()->routeIs('music-tag-manager')" wire:navigate>
                Zenei címkék
            </flux:sidebar.item>
            </flux:sidebar.group>
            @endif
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>

            @if(auth()->check())
            <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" :badge="auth()->user()->unread_notifications_count ? auth()->user()->unread_notifications_count : false" wire:navigate>
                {{ __('Notifications') }}
            </flux:sidebar.item>
            @endif

            @if(auth()->check() && auth()->user()->is_admin)
            <flux:sidebar.item icon="shield-check" :href="route('admin.music-plan-templates')" :current="request()->routeIs('admin.nickname-data')" wire:navigate>
                {{ __('Admin') }}
            </flux:sidebar.item>
            @endif

            <flux:sidebar.item icon="information-circle" :href="route('guide')" :current="request()->routeIs('guide')" wire:navigate>
                Útmutató
            </flux:sidebar.item>

            <flux:sidebar.item
                icon="envelope"
                as="button"
                x-on:click="Livewire.dispatch('openContactModal')"
                wire:ignore>
                {{ __('Contact Us') }}
            </flux:sidebar.item>
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

    <livewire:contact-us />

    @fluxScripts
</body>

</html>