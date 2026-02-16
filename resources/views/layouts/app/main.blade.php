<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <header class="w-full lg:max-w-4xl mx-auto flex items-center justify-between text-sm mb-6">
            <div class="flex items-center gap-4">
                <flux:brand name="Cantores.hu" {{ $attributes }}>
                    <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
                        <x-app-logo-icon class="fill-current text-white dark:text-black" />
                    </x-slot>
                </flux:brand>
            </div>
            @if (Route::has('login'))
                <!-- Desktop navigation (hidden on mobile) -->
                <nav class="hidden lg:flex items-center gap-4">
                    <a href="{{ route('music-plans') }}" class="text-accent hover:underline font-medium text-sm">
                        <flux:icon name="music" class="inline" variant="mini"></flux:icon>
                        Énekrendek
                    </a>
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                        >
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ url('/about') }}" class="text-accent hover:underline font-medium text-sm">
                        <flux:icon name="information-circle" class="inline" variant="mini"></flux:icon>
                        Bemutatkozás</a>

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
                        @endif
                    @endauth
                </nav>
        
                <!-- Mobile hamburger menu (visible on mobile) -->
                <div class="lg:hidden">
                    <flux:dropdown align="end">
                        <flux:button variant="ghost" square icon="bars-3" aria-label="Menu" />
                        <flux:menu>
                            <flux:menu.item href="{{ url('/about') }}" icon="information-circle">
                                Bemutatkozás
                            </flux:menu.item>
                            <flux:menu.item href="{{ route('music-plans') }}" icon="music">
                                Énekrendek
                            </flux:menu.item>
                            @auth
                                <flux:menu.item href="{{ url('/dashboard') }}" icon="home">
                                    Dashboard
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
        <div class="w-full lg:max-w-4xl mx-auto mt-8 flex justify-center">
            <div class="flex items-center">
                <flux:heading class="mr-2">Műfaj:</flux:heading>
                <livewire:genre-selector />
            </div>
        </div>
        <footer class="w-full lg:max-w-4xl mx-auto mt-2 flex flex-col items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
            <div class="items-center">&copy; {{ date('Y') }} Cantores.hu. A fejlesztést a <a href="https://github.com/szentjozsefhackathon/cantores" target="_blank" class="hover:text-blue-500 underline">Szent József Hackathon</a> keretében végezzük.</div>
            <div class="flex items-center gap-1">
                <span class="font-bold text-lg tracking-widest text-accent">U.I.O.G.D.</span>
            </div>
        </footer>
    @fluxScripts
    </body>
</html>
