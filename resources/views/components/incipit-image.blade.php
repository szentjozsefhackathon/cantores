@props([
    'src',
    'alt' => '',
    'imgClass' => '',
    'imgStyle' => '',
])

<div {{ $attributes->merge(['class' => 'relative inline-block group/incipit']) }}
     x-data="{
        incipitZoom: false,
        zoomStyle: '',
        openZoom() {
            this.incipitZoom = true;
        },
        positionZoom() {
            const trigger = this.$refs.incipitTrigger;
            const dialog = this.$refs.incipitZoomDialog;
            if (! trigger || ! dialog || ! dialog.open) {
                return;
            }
            const margin = 12;
            const rect = trigger.getBoundingClientRect();
            const width = dialog.offsetWidth;
            const height = dialog.offsetHeight;
            const cx = Math.min(
                Math.max(rect.left + rect.width / 2, margin + width / 2),
                window.innerWidth - margin - width / 2,
            );
            const cy = Math.min(
                Math.max(rect.top + rect.height / 2, margin + height / 2),
                window.innerHeight - margin - height / 2,
            );
            this.zoomStyle = `left: ${cx}px; top: ${cy}px;`;
        },
     }">
    <img x-ref="incipitTrigger"
         src="{{ $src }}"
         alt="{{ $alt }}"
         class="{{ $imgClass }}"
         @if ($imgStyle) style="{{ $imgStyle }}" @endif />

    <button type="button"
            x-on:click.prevent.stop="openZoom()"
            x-on:mousedown.prevent.stop
            x-on:mouseup.stop
            class="absolute right-0.5 top-0.5 z-20 flex items-center justify-center rounded-md bg-white/80 p-1 text-gray-600 opacity-70 shadow-sm ring-1 ring-gray-200 backdrop-blur-sm transition hover:bg-white hover:text-gray-900 group-hover/incipit:opacity-100 dark:bg-gray-900/70 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-gray-900 dark:hover:text-white"
            :title="'{{ __('View full image') }}'"
            aria-label="{{ __('View full image') }}">
        <flux:icon name="magnifying-glass" class="h-3.5 w-3.5" />
    </button>

    {{-- Rendered as a native <dialog> opened with showModal() so it joins the browser's
         top layer and paints above Flux modals (which are themselves top-layer dialogs).
         Instead of a full-window overlay, it is anchored over the original incipit and
         clamped to the viewport, so the zoom stays where the user is looking. --}}
    <template x-teleport="body">
        <dialog x-ref="incipitZoomDialog"
                x-effect="incipitZoom
                    ? (! $el.open && ($el.showModal(), $nextTick(() => positionZoom())))
                    : ($el.open && ($el.close(), zoomStyle = ''))"
                x-on:close="incipitZoom = false"
                x-on:click="incipitZoom = false"
                x-on:resize.window="positionZoom()"
                :style="zoomStyle"
                :class="zoomStyle ? 'opacity-100' : 'opacity-0'"
                class="fixed left-1/2 top-1/2 z-[100] m-0 max-h-none max-w-none -translate-x-1/2 -translate-y-1/2 rounded bg-white p-0 shadow-2xl ring-1 ring-black/10 backdrop:bg-black/10 dark:bg-gray-900 dark:ring-white/10">
            <img src="{{ $src }}"
                 alt="{{ $alt }}"
                 x-on:click="incipitZoom = false"
                 x-on:load="positionZoom()"
                 class="block max-h-[80vh] max-w-[min(640px,90vw)] cursor-zoom-out rounded" />
            <button type="button"
                    x-on:click="incipitZoom = false"
                    class="absolute right-2 top-2 flex items-center justify-center rounded-full bg-white/90 p-2 text-gray-700 shadow hover:bg-white dark:bg-gray-800/90 dark:text-gray-200 dark:hover:bg-gray-800"
                    aria-label="{{ __('Close') }}">
                <flux:icon name="x-mark" class="h-5 w-5" />
            </button>
        </dialog>
    </template>
</div>
