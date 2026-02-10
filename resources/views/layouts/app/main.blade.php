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
                <a href="{{ url('/about') }}" class="text-accent hover:underline font-medium text-sm">Bemutatkozás</a>
            </div>
            @if (Route::has('login'))
                <nav class="flex items-center gap-4">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                        >
                            {{ __('Log in') }} 
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                                {{ __('Register') }}
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>
        {{ $slot }}
        <footer class="w-full lg:max-w-4xl mx-auto mt-2 flex flex-col items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
            <div>&copy; {{ date('Y') }} Cantores.hu. A fejlesztést a Szent József Hackathon keretében végezzük.</div>
            <div class="flex items-center gap-1">
                <span class="font-bold text-lg tracking-widest text-accent">U.I.O.G.D.</span>
            </div>
        </footer>
    @fluxScripts
    </body>
</html>
