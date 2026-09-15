@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'ogImage' => null, 'jsonLd' => null])
{{--
    The one shell every page asks for. A signed in visitor navigates by the
    sidebar, so they get it everywhere; a guest has no sidebar to navigate with
    and gets the public header instead. Pages that own the whole screen — the
    projection output and the remote that drives it — name `app.main` directly.
--}}
@auth
    <x-layouts::app
        :title="$title"
        :description="$description"
        :canonical="$canonical"
        :noindex="$noindex"
        :og-image="$ogImage"
        :json-ld="$jsonLd"
    >
        {{ $slot }}
    </x-layouts::app>
@else
    <x-layouts::app.main
        :title="$title"
        :description="$description"
        :canonical="$canonical"
        :noindex="$noindex"
        :og-image="$ogImage"
        :json-ld="$jsonLd"
    >
        {{ $slot }}
    </x-layouts::app.main>
@endauth
