@props(['on' => null])

@if (trim($slot) !== '')
    <div
        x-data="{ shown: false, timeout: null }"
        x-init="@this.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 3000); })"
        x-show="shown"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-4"
        class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 bg-yellow-200 text-yellow-900 px-6 py-3 rounded shadow-lg border border-yellow-300"
        style="min-width: 300px; max-width: 90vw;"
        x-cloak
    >
        {{ $slot->isEmpty() ? __('Saved.') : $slot }}
    </div>
@endif