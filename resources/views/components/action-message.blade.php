@props(['on' => null])

@php
    $isError = $on === 'error';
    $classes = \Illuminate\Support\Arr::toCssClasses([
        'fixed top-4 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded shadow-lg border',
        'bg-green-100 text-green-800 border-green-300 dark:bg-green-900 dark:text-green-300 dark:border-green-800' => ! $isError,
        'bg-red-100 text-red-800 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-800' => $isError,
    ]);
@endphp

<div
    x-data="{ shown: false, timeout: null, message: event.message || '{{ $slot->isEmpty() ? __('Saved.') : $slot }}' }"
    x-init="
            @if($on === 'notify')
                @this.on('{{ $on }}', (event) => {
                    clearTimeout(timeout);
                    message = event.message || '{{ $slot->isEmpty() ? __('Saved.') : $slot }}';
                    shown = true;
                    timeout = setTimeout(() => { shown = false }, 3000);
                })
            @else
                @this.on('{{ $on }}', (event) => {
                    clearTimeout(timeout);
                    message = event.message || '{{ $slot->isEmpty() ? __('Saved.') : $slot }}';
                    shown = true;
                    timeout = setTimeout(() => { shown = false }, 3000);
                })
            @endif
        "
    x-show="shown"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-4"
    {{ $attributes->merge(['class' => $classes]) }}
    style="min-width: 300px; max-width: 90vw;"
    x-cloak>
    <span x-text="message"></span>
</div>