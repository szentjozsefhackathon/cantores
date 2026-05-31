@props([
    'src',
    'alt' => '',
    'imgClass' => '',
    'imgStyle' => '',
])

<div {{ $attributes->merge(['class' => 'relative inline-block group/incipit']) }}
     x-data="{ incipitZoom: false }">
    <img src="{{ $src }}"
         alt="{{ $alt }}"
         class="{{ $imgClass }}"
         @if ($imgStyle) style="{{ $imgStyle }}" @endif />

    <button type="button"
            x-on:click.prevent.stop="incipitZoom = true"
            class="absolute right-0.5 top-0.5 z-20 flex items-center justify-center rounded-md bg-white/80 p-1 text-gray-600 opacity-70 shadow-sm ring-1 ring-gray-200 backdrop-blur-sm transition hover:bg-white hover:text-gray-900 group-hover/incipit:opacity-100 dark:bg-gray-900/70 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-gray-900 dark:hover:text-white"
            :title="'{{ __('View full image') }}'"
            aria-label="{{ __('View full image') }}">
        <flux:icon name="magnifying-glass" class="h-3.5 w-3.5" />
    </button>

    {{-- Rendered as a native <dialog> opened with showModal() so it joins the browser's
         top layer and paints above Flux modals (which are themselves top-layer dialogs). --}}
    <template x-teleport="body">
        <dialog x-ref="incipitZoomDialog"
                x-effect="incipitZoom ? (! $el.open && $el.showModal()) : ($el.open && $el.close())"
                x-on:close="incipitZoom = false"
                x-on:click="incipitZoom = false"
                class="fixed inset-0 z-[100] m-0 h-full max-h-none w-full max-w-none items-center justify-center bg-black/80 p-4 backdrop:bg-transparent open:flex">
            <img src="{{ $src }}"
                 alt="{{ $alt }}"
                 x-on:click.stop
                 class="max-h-[90vh] max-w-[95vw] rounded bg-white shadow-2xl" />
            <button type="button"
                    x-on:click="incipitZoom = false"
                    class="absolute right-4 top-4 flex items-center justify-center rounded-full bg-white/90 p-2 text-gray-700 shadow hover:bg-white dark:bg-gray-800/90 dark:text-gray-200 dark:hover:bg-gray-800"
                    aria-label="{{ __('Close') }}">
                <flux:icon name="x-mark" class="h-5 w-5" />
            </button>
        </dialog>
    </template>
</div>
