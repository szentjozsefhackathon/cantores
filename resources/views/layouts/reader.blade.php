@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'ogImage' => null, 'jsonLd' => null])
{{--
    A page read from someone else's link — the band's booklet on a music stand.
    It is not the reader's own workspace, so the sidebar that navigates their
    workspace has no place here, nor does the public site's header: the screen
    belongs to what is being read. A signed in reader keeps a slim bar with the
    way back to their dashboard and their own menu; a guest gets the page alone.
--}}
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
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        @auth
            <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" data-reader-header>
                <x-app-logo href="{{ route('home') }}" />

                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="home"
                    :href="route('dashboard')"
                    class="ms-2">
                    <span class="max-sm:sr-only">{{ __('Dashboard') }}</span>
                </flux:button>

                <flux:spacer />

                <div x-data="{ cycle() { const s = ['light','dark','system']; $flux.appearance = s[(s.indexOf($flux.appearance) + 1) % 3]; } }">
                    <flux:button variant="ghost" square @click="cycle()" aria-label="Toggle appearance">
                        <flux:icon x-show="$flux.appearance === 'light'" name="sun" variant="mini" />
                        <flux:icon x-show="$flux.appearance === 'dark'" name="moon" variant="mini" />
                        <flux:icon x-show="$flux.appearance === 'system'" name="computer-desktop" variant="mini" />
                    </flux:button>
                </div>

                <x-header-user-menu />
            </flux:header>
        @endauth

        {{ $slot }}

        @fluxScripts
    </body>
</html>
