@props([
    'ownerName' => '',
    'canKeep' => false,
    'kept' => false,
])

{{--
    The bar across the top of anything read through a lending link.

    It carries three things that have to be said out loud: whose work this is,
    that the lender can see it was opened, and where to put it so it can be found
    again. Saving is a bar rather than a buried icon because a list nobody knows
    how to fill stays empty forever.
--}}
<div {{ $attributes->merge(['class' => 'mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/30']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <flux:badge color="amber" size="sm" icon="arrow-path-rounded-square">{{ __('On loan') }}</flux:badge>
                @if($ownerName !== '')
                    <flux:text class="text-sm">
                        {{ __(':name lent you this.', ['name' => $ownerName]) }}
                    </flux:text>
                @endif
            </div>
            <flux:text class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                @if($canKeep)
                    {{ __('The lender can see that you opened it.') }}
                @else
                    {{ __('Sign in to save this among your loans.') }}
                @endif
            </flux:text>
        </div>

        @if($canKeep)
            <div class="shrink-0">
                @if($kept)
                    <flux:badge color="green" size="sm" icon="check">{{ __('Saved to your loans') }}</flux:badge>
                @else
                    <flux:button size="sm" variant="primary" icon="bookmark" wire:click="keep">
                        {{ __('Save to my loans') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </div>
</div>
