<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6">
                <flux:heading size="2xl">{{ __('Loans') }}</flux:heading>
                <flux:subheading>
                    {{ __('A loan is not a gift: the score stays its owner\'s, and they can recall it at any time.') }}
                </flux:subheading>
            </div>

            <div class="mb-6 flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
                @foreach([
                    \App\Livewire\Pages\Loans::TAB_BORROWED => __('Borrowed scores'),
                    \App\Livewire\Pages\Loans::TAB_LENT => __('Lent scores'),
                    \App\Livewire\Pages\Loans::TAB_PUBLISHED => __('My published scores'),
                ] as $tabKey => $tabLabel)
                    <flux:button
                        size="sm"
                        :variant="$tab === $tabKey ? 'primary' : 'ghost'"
                        wire:click="selectTab('{{ $tabKey }}')"
                        wire:key="loan-tab-{{ $tabKey }}">
                        {{ $tabLabel }}
                        <span class="ms-1.5 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $tab === $tabKey ? 'bg-accent-foreground/20 text-accent-foreground' : 'bg-zinc-400/15 text-zinc-700 dark:bg-zinc-400/40 dark:text-zinc-200' }}">{{ $this->tabCounts[$tabKey] }}</span>
                    </flux:button>
                @endforeach
            </div>

            @if($tab === \App\Livewire\Pages\Loans::TAB_BORROWED)
                @include('livewire.pages.loans.borrowed')
            @elseif($tab === \App\Livewire\Pages\Loans::TAB_LENT)
                @include('livewire.pages.loans.lent')
            @else
                @include('livewire.pages.loans.published')
            @endif
        </flux:card>
    </div>
</div>
