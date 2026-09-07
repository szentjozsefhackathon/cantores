@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'ogImage' => null, 'jsonLd' => null])
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
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <header class="w-full lg:max-w-4xl mx-auto flex items-center justify-between gap-2 text-sm px-4 py-3 sm:px-6 lg:px-8 mb-6">
            <div class="flex items-center gap-4">
                {{-- Mobile: icon only --}}
                <div class="lg:hidden">
                    <flux:brand {{ $attributes }}>
                        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
                            <x-app-logo-icon class="fill-current text-white dark:text-black" />
                        </x-slot>
                    </flux:brand>
                </div>
                {{-- Desktop: icon + name --}}
                <div class="hidden lg:block">
                    <flux:brand name="Cantores.hu" {{ $attributes }}>
                        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
                            <x-app-logo-icon class="fill-current text-white dark:text-black" />
                        </x-slot>
                    </flux:brand>
                </div>
            </div>
            @if (Route::has('login'))
                <!-- Desktop navigation (hidden on mobile) -->
                <nav class="hidden lg:flex items-center gap-4">
                    <a href="{{ route('music-database') }}" class="text-accent hover:underline font-medium text-sm">
                        <flux:icon name="circle-stack" class="inline" variant="mini"></flux:icon>
                        Énektár
                    </a>
                    <a href="{{ route('music-plans') }}" class="text-accent hover:underline font-medium text-sm">
                        <flux:icon name="list-music" class="inline" variant="mini"></flux:icon>
                        Énekrendek
                    </a>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon-trailing="chevron-down" class="text-accent! font-medium text-sm!">
                            <flux:icon name="arrow-down-tray" class="inline" variant="mini"></flux:icon>
                            Kottatár
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item href="{{ route('public-scores') }}" icon="arrow-down-tray">
                                Ingyenes kották
                            </flux:menu.item>
                            @guest
                                <flux:menu.item href="{{ route('score.preview') }}" icon="musical-note">
                                    Kottaszerkesztő
                                </flux:menu.item>
                            @endguest
                        </flux:menu>
                    </flux:dropdown>
                    @auth
                        <a href="{{ url('/dashboard') }}">
                            <flux:button variant="primary" icon="home">{{ __('Dashboard') }}</flux:button>
                        </a>

                        <div class="flex items-center" x-data="{ cycle() { const s = ['light','dark','system']; $flux.appearance = s[(s.indexOf($flux.appearance) + 1) % 3]; } }">
                            <flux:button variant="ghost" square @click="cycle()" aria-label="Toggle appearance">
                                <flux:icon x-show="$flux.appearance === 'light'" name="sun" variant="mini" />
                                <flux:icon x-show="$flux.appearance === 'dark'" name="moon" variant="mini" />
                                <flux:icon x-show="$flux.appearance === 'system'" name="computer-desktop" variant="mini" />
                            </flux:button>
                        </div>
                    @else
                        <a href="{{ url('/about') }}" class="text-accent hover:underline font-medium text-sm">
                            <flux:icon name="information-circle" class="inline" variant="mini"></flux:icon>
                            Rólunk
                        </a>

                        <a
                            href="{{ route('login') }}"
                            class="text-accent hover:underline font-medium text-sm"
                        >
                            <flux:icon name="log-in" class="inline" variant="mini"></flux:icon>
                            {{ __('Log in') }}
                        </a>

                        <div class="flex items-center" x-data="{ cycle() { const s = ['light','dark','system']; $flux.appearance = s[(s.indexOf($flux.appearance) + 1) % 3]; } }">
                            <flux:button variant="ghost" square @click="cycle()" aria-label="Toggle appearance">
                                <flux:icon x-show="$flux.appearance === 'light'" name="sun" variant="mini" />
                                <flux:icon x-show="$flux.appearance === 'dark'" name="moon" variant="mini" />
                                <flux:icon x-show="$flux.appearance === 'system'" name="computer-desktop" variant="mini" />
                            </flux:button>
                        </div>
                    @endauth
                </nav>
        
                {{--
                    Mobile navigation. The same three destinations as the desktop
                    nav, but the labels collapse to their icons below `sm` so the
                    row still fits on a narrow phone; the label stays in the
                    markup for screen readers.
                --}}
                <nav class="lg:hidden flex items-center gap-1 sm:gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}">
                            <flux:button variant="primary" icon="home" size="sm" class="max-sm:px-2!">
                                <span class="max-sm:sr-only">Irányítópult</span>
                            </flux:button>
                        </a>
                    @endauth
                    <a href="{{ route('music-database') }}" class="inline-flex items-center gap-1 p-1 text-accent hover:underline font-medium text-sm">
                        <flux:icon name="circle-stack" variant="mini"></flux:icon>
                        <span class="max-sm:sr-only">Énektár</span>
                    </a>
                    <a href="{{ route('music-plans') }}" class="inline-flex items-center gap-1 p-1 text-accent hover:underline font-medium text-sm">
                        <flux:icon name="list-music" variant="mini"></flux:icon>
                        <span class="max-sm:sr-only">Énekrendek</span>
                    </a>
                    <flux:dropdown align="end">
                        <flux:button variant="ghost" size="sm" icon="arrow-down-tray" icon-trailing="chevron-down" class="text-accent! font-medium text-sm! max-sm:px-1.5! max-sm:gap-0!">
                            <span class="max-sm:sr-only">Kottatár</span>
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item href="{{ route('public-scores') }}" icon="arrow-down-tray">
                                Ingyenes kották
                            </flux:menu.item>
                            @guest
                                <flux:menu.item href="{{ route('score.preview') }}" icon="musical-note">
                                    Kottaszerkesztő
                                </flux:menu.item>
                            @endguest
                        </flux:menu>
                    </flux:dropdown>
                    <flux:dropdown align="end">
                        <flux:button variant="ghost" size="sm" square icon="bars-3" aria-label="Menü" />
                        <flux:menu>
                            <flux:menu.item href="{{ url('/about') }}" icon="information-circle">
                                Rólunk
                            </flux:menu.item>
                            @guest
                                <flux:menu.item href="{{ route('login') }}" icon="log-in">
                                    {{ __('Log in') }}
                                </flux:menu.item>
                            @endguest
                            <flux:menu.separator />
                            <flux:menu.radio.group x-model="$flux.appearance">
                                <flux:menu.radio value="light"><flux:icon name="sun" class="inline" variant="mini"></flux:icon></flux:menu.radio>
                                <flux:menu.radio value="dark"><flux:icon name="moon" class="inline" variant="mini"></flux:icon></flux:menu.radio>
                                <flux:menu.radio value="system"><flux:icon name="computer-desktop" class="inline" variant="mini"></flux:icon></flux:menu.radio>
                            </flux:menu.radio.group>
                        </flux:menu>
                    </flux:dropdown>
                </nav>
            @endif
        </header>
        {{ $slot }}
        <footer class="w-full lg:max-w-4xl mx-auto mt-2 flex flex-col items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
            <div class="items-center">&copy; {{ date('Y') }} Cantores.hu. A fejlesztést a <a href="https://github.com/szentjozsefhackathon/" target="_blank" class="hover:text-blue-500 underline">Szent József Hackathon</a> keretében végezzük.
                <a href="https://aretino-chant.github.io" target="_blank" class="hover:text-blue-500 underline">Aretino Chant</a> – saját kottázási rendszerünkkel.
                @if(config('version.hash'))
                    <span class="text-zinc-400 dark:text-zinc-600">
                        Verzió: <a href="https://github.com/szentjozsefhackathon/cantores/commit/{{ config('version.hash') }}" target="_blank">{{ substr(config('version.hash'), 0, 7) }}</a>
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-1">
                <span class="font-bold text-lg tracking-widest text-accent">U.I.O.G.D.</span>
            </div>
        </footer>
    @fluxScripts
    </body>
</html>
