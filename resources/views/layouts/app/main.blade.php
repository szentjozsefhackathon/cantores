@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false])
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
        <header class="w-full lg:max-w-4xl mx-auto flex items-center justify-between text-sm mb-6">
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
                        Rólunk</a>

                        <a
                            href="{{ route('login') }}"
                            class="text-accent hover:underline font-medium text-sm"
                        >
                            <flux:icon name="log-in" class="inline" variant="mini"></flux:icon>
                            {{ __('Log in') }}
                        </a>
        
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                                <flux:icon name="user-plus" class="inline" variant="mini"></flux:icon>

                                {{ __('Register') }}
                            </a>
                            
                            <div class="flex items-center" x-data="{ cycle() { const s = ['light','dark','system']; $flux.appearance = s[(s.indexOf($flux.appearance) + 1) % 3]; } }">
                                <flux:button variant="ghost" square @click="cycle()" aria-label="Toggle appearance">
                                    <flux:icon x-show="$flux.appearance === 'light'" name="sun" variant="mini" />
                                    <flux:icon x-show="$flux.appearance === 'dark'" name="moon" variant="mini" />
                                    <flux:icon x-show="$flux.appearance === 'system'" name="computer-desktop" variant="mini" />
                                </flux:button>
                            </div>
                        @endif
                    @endauth
                </nav>
        
                <!-- Mobile hamburger menu (visible on mobile) -->
                <div class="lg:hidden flex items-center gap-2">
                    @auth
                        <a href="{{ url('/dashboard') }}">
                            <flux:button variant="primary" icon="home" size="sm">Irányítópult</flux:button>
                        </a>
                    @endauth
                    <a href="{{ route('music-database') }}" class="text-accent hover:underline font-medium text-sm">
                        <flux:icon name="circle-stack" class="inline" variant="mini"></flux:icon>
                        Énektár
                    </a>
                    <a href="{{ route('music-plans') }}" class="text-accent hover:underline font-medium text-sm">
                        <flux:icon name="list-music" class="inline" variant="mini"></flux:icon>
                        Énekrendek
                    </a>
                    <flux:dropdown align="end">
                        <flux:button variant="ghost" square icon="bars-3" aria-label="Menu" />
                        <flux:menu>
                            <flux:menu.item href="{{ url('/about') }}" icon="information-circle">
                                Rólunk
                            </flux:menu.item>
                            
                            <flux:menu.separator />
                            <flux:menu.radio.group x-model="$flux.appearance">
                                <flux:menu.radio value="light"><flux:icon name="sun" class="inline" variant="mini"></flux:icon></flux:menu.radio>
                                <flux:menu.radio value="dark"><flux:icon name="moon" class="inline" variant="mini"></flux:icon></flux:menu.radio>
                                <flux:menu.radio value="system"><flux:icon name="computer-desktop" class="inline" variant="mini"></flux:icon></flux:menu.radio>
                            </flux:menu.radio.group>
                            <flux:menu.separator />
                            
                            @auth
                                <flux:menu.item href="{{ url('/dashboard') }}" icon="home">
                                    Irányítópult
                                </flux:menu.item>
                            @else
                                <flux:menu.item href="{{ route('login') }}" icon="log-in">
                                    {{ __('Log in') }}
                                </flux:menu.item>
                                @if (Route::has('register'))
                                    <flux:menu.item href="{{ route('register') }}" icon="user-plus">
                                        {{ __('Register') }}
                                    </flux:menu.item>
                                @endif
                            @endauth
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @endif
        </header>
        {{ $slot }}
        <footer class="w-full lg:max-w-4xl mx-auto mt-2 flex flex-col items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
            <div class="items-center">&copy; {{ date('Y') }} Cantores.hu. A fejlesztést a <a href="https://github.com/szentjozsefhackathon/cantores" target="_blank" class="hover:text-blue-500 underline">Szent József Hackathon</a> keretében végezzük.
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
