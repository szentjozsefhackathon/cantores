<div class="space-y-2">
    @foreach($this->loans as $loan)
        <div
            class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
            wire:key="loan-link-{{ $loan->id }}"
            x-data="{ copied: false }">
            <div class="flex items-center gap-2">
                <flux:input
                    size="sm"
                    :value="$loan->label"
                    :placeholder="__('Name this link, e.g. “Band”')"
                    x-on:change="$wire.rename({{ $loan->id }}, $event.target.value)"
                    class="min-w-0 flex-1" />
                <flux:button
                    size="sm"
                    icon="arrow-uturn-left"
                    variant="ghost"
                    :title="__('Recall this link')"
                    wire:click="recall({{ $loan->id }})"
                    wire:confirm="{{ __('Recall this link? Anyone still holding it will lose access.') }}" />
            </div>

            <div class="mt-2 flex items-center gap-2">
                <flux:input size="sm" readonly :value="$loan->url()" class="min-w-0 flex-1 font-mono text-xs" />
                <flux:button
                    size="sm"
                    icon="clipboard"
                    variant="ghost"
                    :title="__('Copy link')"
                    x-on:click="navigator.clipboard.writeText(@js($loan->url())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                    x-bind:class="copied ? 'text-green-600' : ''" />
            </div>

            <div class="mt-2 flex flex-wrap gap-1">
                <flux:button
                    size="xs"
                    variant="ghost"
                    :icon="$loan->restricted ? 'lock-closed' : 'globe-alt'"
                    :href="route('loans.recipients', ['loan' => $loan->id])"
                    wire:navigate>
                    {{ $loan->restricted
                        ? trans_choice('{0} Only you|[1,*] :count people only', $loan->recipients_count, ['count' => $loan->recipients_count])
                        : __('Anyone with the link') }}
                </flux:button>
                @if($loan->isContainer())
                    <flux:button
                        size="xs"
                        variant="ghost"
                        icon="adjustments-horizontal"
                        :href="route('loans.manage', ['loan' => $loan->id])"
                        wire:navigate>
                        {{ __('What this loan opens') }}
                    </flux:button>
                @endif
            </div>
        </div>
    @endforeach

    <flux:button size="sm" icon="link" variant="ghost" wire:click="lend">
        {{ $this->loans->isEmpty() ? __('Lend by link') : __('New link') }}
    </flux:button>
</div>
