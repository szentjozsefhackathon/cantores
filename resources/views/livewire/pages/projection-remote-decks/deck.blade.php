{{-- One deck that could go up. --}}
@php($current = $this->showing?->projection_id === $projection->id)

<button
    type="button"
    wire:key="projection-{{ $key }}-{{ $projection->id }}"
    wire:click="present({{ $projection->id }})"
    @class([
        'flex w-full items-center gap-3 rounded-lg border px-4 py-3 text-start',
        'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800' => $current,
        'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' => ! $current,
    ])
>
    <flux:icon.presentation class="size-5 shrink-0 text-zinc-400" />

    <div class="min-w-0 flex-1">
        <div class="truncate font-medium">{{ $projection->title }}</div>
        <div class="text-xs text-zinc-500">{{ $projection->updated_at->diffForHumans() }}</div>
    </div>

    @if($current)
        <span class="shrink-0 text-xs font-medium text-zinc-500">{{ __('On the screen') }}</span>
    @endif

    {{-- The deck has to be engraved before the room can see it, which is
         seconds rather than frames, so the screen goes black while it happens.
         Said here because the person tapping is the one person who cannot see
         the screen. --}}
    <flux:icon.chevron-right class="size-5 shrink-0 text-zinc-400" wire:loading.remove wire:target="present({{ $projection->id }})" />
    <flux:icon.loading class="size-5 shrink-0 text-zinc-400" wire:loading wire:target="present({{ $projection->id }})" />
</button>
