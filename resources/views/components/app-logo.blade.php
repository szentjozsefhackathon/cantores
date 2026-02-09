@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Cantores.hu" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
            <x-app-logo-icon class="fill-current text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Cantores.hu" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
            <x-app-logo-icon class="fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
