@props([
    'title',
    'description' => null,
    'inline' => false,
])

@if ($inline)
    <a href="{{ route('home') }}" class="flex w-full items-center justify-center gap-2 text-lg" wire:navigate>
        <x-app-logo-icon class="h-[1em] w-[1.22em] shrink-0 fill-current text-black dark:text-white" />
        <flux:heading size="lg">{{ $title }}</flux:heading>
        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
    </a>
@else
    <div class="flex w-full flex-col text-center">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($description)
            <flux:subheading>{{ $description }}</flux:subheading>
        @endif
    </div>
@endif
