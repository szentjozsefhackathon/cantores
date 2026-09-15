@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'ogImage' => null, 'jsonLd' => null])
<x-layouts::app.sidebar
    :title="$title"
    :description="$description"
    :canonical="$canonical"
    :noindex="$noindex"
    :og-image="$ogImage"
    :json-ld="$jsonLd"
>
    <flux:main class="p-0! sm:p-2! lg:p-4!">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
