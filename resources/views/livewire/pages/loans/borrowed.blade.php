{{-- What other people lent me and I chose to keep. --}}
@php($receipts = $this->borrowed)

@if($receipts->isEmpty())
    <flux:callout variant="secondary" icon="bookmark" class="border-dashed">
        <flux:callout.heading>{{ __('Nothing borrowed yet') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('When someone lends you a score, folder or music plan, save it from the page they sent you and it will wait for you here.') }}
        </flux:callout.text>
    </flux:callout>
@else
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Borrowed') }}</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">{{ __('Lender') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Last changed') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Access') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach($receipts as $receipt)
                @php($described = $this->describeReceipt($receipt))
                @php($ended = $this->endedReason($receipt->loan))
                <flux:table.row wire:key="received-loan-{{ $receipt->id }}">
                    <flux:table.cell>
                        <div class="font-medium">
                            @if($described['url'] && ! $ended)
                                <a href="{{ $described['url'] }}" class="hover:underline">{{ $described['title'] }}</a>
                            @else
                                {{ $described['title'] }}
                            @endif
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                            <flux:badge color="zinc" size="sm">{{ $described['type'] }}</flux:badge>
                            @if($receipt->loan?->expires_at)
                                <flux:badge color="amber" size="sm" icon="clock">
                                    {{ __('Until :date', ['date' => $receipt->loan->expires_at->translatedFormat('Y-m-d')]) }}
                                </flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $described['owner'] }}</span>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $described['changed_at']?->translatedFormat('Y-m-d') ?? '—' }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        @if($ended)
                            <flux:badge color="red" size="sm">{{ $ended }}</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">{{ __('Open') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            @if(! $ended && $this->keptScore($receipt) && $this->myFolders->isNotEmpty())
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="folder-plus">{{ __('Add to a folder') }}</flux:button>
                                    <flux:menu>
                                        @foreach($this->myFolders as $folder)
                                            <flux:menu.item wire:click="addToFolder({{ $receipt->id }}, {{ $folder->id }})">
                                                {{ $folder->name }}
                                            </flux:menu.item>
                                        @endforeach
                                    </flux:menu>
                                </flux:dropdown>
                            @endif

                            @if($ended && $receipt->loan)
                                <flux:button size="sm" variant="ghost" icon="hand-raised" wire:click="askAgain({{ $receipt->id }})">
                                    {{ __('Ask again') }}
                                </flux:button>
                            @endif
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye-slash"
                                wire:click="hide({{ $receipt->id }})"
                                wire:confirm="{{ __('Remove this from your loans? You can save it again from the link.') }}">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if($receipts->hasPages())
        <div class="mt-4">{{ $receipts->links() }}</div>
    @endif
@endif
