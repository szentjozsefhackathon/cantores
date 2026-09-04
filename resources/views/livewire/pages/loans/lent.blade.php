{{-- What I lent, how far it travelled, and how to take it back. --}}
@php($loans = $this->lent)

@if($loans->isEmpty())
    <flux:callout variant="secondary" icon="link" class="border-dashed">
        <flux:callout.heading>{{ __('You have not lent anything yet') }}</flux:callout.heading>
        <flux:callout.text>{{ __('Lend a score, folder or music plan to create a lending link.') }}</flux:callout.text>
    </flux:callout>
@else
    <flux:callout variant="secondary" icon="information-circle" class="mb-4">
        <flux:callout.text>
            {{ __('A folder or music plan link also opens the scores it contains. Recalling it closes those too.') }}
        </flux:callout.text>
    </flux:callout>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Lent') }}</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">{{ __('Type') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('Reach') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('State') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach($loans as $loan)
                @php($described = $this->describe($loan))
                @php($reach = $this->reach($loan))
                @php($ended = $this->endedReason($loan))
                <flux:table.row wire:key="loan-row-{{ $loan->id }}">
                    <flux:table.cell>
                        <div class="font-medium">
                            @if($described['url'])
                                <a href="{{ $described['url'] }}" wire:navigate class="hover:underline">{{ $described['title'] }}</a>
                            @else
                                {{ $described['title'] }}
                            @endif
                        </div>
                        <div class="mt-0.5 truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $this->linkFor($loan) }}
                        </div>
                        @if($loan->isContainer())
                            <div class="mt-1">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="adjustments-horizontal"
                                    :href="route('loans.manage', ['loan' => $loan->id])"
                                    wire:navigate>
                                    {{ __(':count scores in this loan', ['count' => $this->reachedScoreCount($loan)]) }}
                                </flux:button>
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">
                        <flux:badge color="zinc" size="sm">{{ $described['type'] }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        <button
                            type="button"
                            class="text-start text-sm text-zinc-600 hover:underline dark:text-zinc-400"
                            wire:click="toggleOpeners({{ $loan->id }})">
                            {{ __(':opens opens · :known known · :anonymous anonymous', [
                                'opens' => $reach['opens'],
                                'known' => $reach['known'],
                                'anonymous' => $reach['anonymous'],
                            ]) }}
                        </button>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __(':kept kept it · :passed passed it on', [
                                'kept' => $reach['kept'],
                                'passed' => $reach['passed_on'],
                            ]) }}
                        </div>

                        @if($expandedLoanId === $loan->id)
                            <div class="mt-2 space-y-1 rounded-md bg-zinc-50 p-2 dark:bg-zinc-800/60">
                                @forelse($this->openers($loan) as $opener)
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span>{{ $opener->user?->displayName ?? __('Unknown') }}</span>
                                        <span class="text-zinc-500 dark:text-zinc-400">
                                            {{ $opener->last_opened_at?->translatedFormat('Y-m-d H:i') }}
                                        </span>
                                    </div>
                                @empty
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Nobody has opened this while signed in.') }}
                                    </div>
                                @endforelse
                                <div class="pt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Readers who were not signed in stay anonymous, so this is never the whole list.') }}
                                </div>
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        @if($ended)
                            <flux:badge color="red" size="sm">{{ $ended }}</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">{{ __('Open') }}</flux:badge>
                        @endif
                        @if($loan->expires_at)
                            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Until :date', ['date' => $loan->expires_at->translatedFormat('Y-m-d')]) }}
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @unless($ended)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="arrow-uturn-left"
                                wire:click="revoke({{ $loan->id }})"
                                wire:confirm="{{ __('Recall this loan? Anyone still holding the link will lose access.') }}">
                                {{ __('Recall') }}
                            </flux:button>
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if($loans->hasPages())
        <div class="mt-4">{{ $loans->links() }}</div>
    @endif
@endif
