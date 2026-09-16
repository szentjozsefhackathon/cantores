<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            {{-- The laptop's and the phone's way in, first thing on the page
                 and full-width on a phone screen, because these are the two
                 controls someone reaches for at the start of Mass rather than
                 while building next Sunday's decks. Both stay quiet until
                 there is something to be quiet about: a screen or a remote
                 with nothing live behind it opens onto an empty room, and
                 bootstrapping one is the slide deck's job, not this page's. --}}
            <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-stretch">
                <flux:tooltip :content="$this->currentPresentation ? __('See what the room is looking at.') : __('Nothing is being projected right now.')">
                    <flux:button
                        :href="$this->currentPresentation ? route('projection-screen') : null"
                        wire:navigate
                        variant="{{ $this->currentPresentation ? 'primary' : 'ghost' }}"
                        icon="tv"
                        class="w-full sm:w-auto {{ $this->currentPresentation ? '' : 'pointer-events-none opacity-50' }}"
                        :aria-disabled="$this->currentPresentation ? null : 'true'"
                    >
                        {{ __('Projection screen') }}
                    </flux:button>
                </flux:tooltip>

                <flux:tooltip :content="$this->currentPresentation ? __('Drive the deck that is up right now.') : __('Nothing is being projected right now.')">
                    <flux:button
                        :href="$this->currentPresentation ? route('projection-remote') : null"
                        wire:navigate
                        variant="{{ $this->currentPresentation ? 'primary' : 'ghost' }}"
                        icon="presentation"
                        class="w-full sm:w-auto {{ $this->currentPresentation ? '' : 'pointer-events-none opacity-50' }}"
                        :aria-disabled="$this->currentPresentation ? null : 'true'"
                    >
                        {{ __('Remote') }}
                    </flux:button>
                </flux:tooltip>

                @if($this->currentPresentation)
                    <flux:callout variant="secondary" icon="tv" inline class="flex-1">
                        <flux:callout.heading>{{ __('Currently projecting') }}</flux:callout.heading>
                        <flux:callout.text>{{ $this->currentPresentation->projection->title }}</flux:callout.text>
                        <x-slot:actions>
                            <flux:button
                                size="sm"
                                :href="route('projections.edit', ['projection' => $this->currentPresentation->projection_id])"
                                wire:navigate
                            >
                                {{ __('Open deck') }}
                            </flux:button>
                        </x-slot:actions>
                    </flux:callout>
                @endif
            </div>

            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('Booklets & Projections') }}</flux:heading>
                    <flux:subheading>{{ __('Everything your music plans have been made into — the pages in the band\'s hands and the slides on the wall, service by service.') }}</flux:subheading>
                </div>

                {{-- Rare, next to the button inside every plan's own column:
                     starting a document that is not for any of them, or for a
                     plan too far back to be on this page. --}}
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="plus" :aria-label="__('New document')" />

                    <flux:menu>
                        <flux:modal.trigger name="new-plan-document">
                            <flux:menu.item icon="book-plus" wire:click="setNewType('booklet')">
                                {{ __('New Booklet') }}
                            </flux:menu.item>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="new-plan-document">
                            <flux:menu.item icon="presentation-plus" wire:click="setNewType('projection')">
                                {{ __('New Projection') }}
                            </flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>
            </div>

            <div class="mb-6">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input type="search" wire:model.live.debounce.500ms="search" icon="magnifying-glass" :placeholder="__('Search')" />
                </flux:field>
            </div>

            @if($plans->isEmpty() && $this->planless['booklets']->isEmpty() && $this->planless['projections']->isEmpty())
                <flux:callout variant="secondary" icon="book-open" class="border-dashed">
                    <flux:callout.heading>{{ __('Nothing built yet') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Open a music plan and press Booklet or Projection to build one from its scores.') }}</flux:callout.text>
                </flux:callout>
            @else
                <div class="space-y-4">
                    @foreach($plans as $plan)
                        <div wire:key="plan-group-{{ $plan->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                                <a href="{{ route('music-plan-view', ['musicPlan' => $plan->id]) }}" wire:navigate class="block truncate font-medium hover:underline">
                                    {{ $plan->celebration_name ?: __('Untitled plan') }}
                                </a>
                                @if($plan->actual_date)
                                    <span class="text-xs text-zinc-500">{{ $plan->actual_date->translatedFormat('Y. F j.') }}</span>
                                @endif
                            </div>

                            {{-- The point of the whole screen: this service's
                                 pages and this service's slides, side by side,
                                 so nobody has to guess which deck was cut from
                                 which booklet. Each column carries its own
                                 create button, because a column with nothing
                                 in it yet is exactly where that button
                                 belongs. --}}
                            <div class="grid gap-px bg-zinc-200 sm:grid-cols-2 dark:bg-zinc-700">
                                <x-plan-document-column
                                    :documents="$plan->booklets"
                                    type="booklet"
                                    :heading="__('Booklets')"
                                    icon="book-open"
                                    :empty="__('No booklet for this service.')"
                                    :plan="$plan"
                                />
                                <x-plan-document-column
                                    :documents="$plan->projections"
                                    type="projection"
                                    :heading="__('Projections')"
                                    icon="presentation"
                                    :empty="__('No projection for this service.')"
                                    :plan="$plan"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($plans->hasPages())
                    <div class="mt-4">
                        {{ $plans->links() }}
                    </div>
                @endif

                @if($this->planless['booklets']->isNotEmpty() || $this->planless['projections']->isNotEmpty())
                    <div class="mt-6 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
                        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                            <span class="font-medium">{{ __('Without a plan') }}</span>
                        </div>
                        <div class="grid gap-px bg-zinc-200 sm:grid-cols-2 dark:bg-zinc-700">
                            <x-plan-document-column
                                :documents="$this->planless['booklets']"
                                type="booklet"
                                :heading="__('Booklets')"
                                icon="book-open"
                                :empty="__('No booklet for this service.')"
                            />
                            <x-plan-document-column
                                :documents="$this->planless['projections']"
                                type="projection"
                                :heading="__('Projections')"
                                icon="presentation"
                                :empty="__('No projection for this service.')"
                            />
                        </div>
                    </div>
                @endif
            @endif
        </flux:card>
    </div>

    {{-- Both documents are the scores of one service, so both start from that
         service's plan. --}}
    <flux:modal name="new-plan-document" class="w-full max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">
                    {{ $newType === 'projection' ? __('New Projection') : __('New Booklet') }}
                </flux:heading>
                <flux:subheading>{{ __('Choose the music plan the document is for.') }}</flux:subheading>
            </div>

            <flux:input
                type="search"
                wire:model.live.debounce.400ms="planSearch"
                icon="magnifying-glass"
                :placeholder="__('Search celebrations')"
            />

            <div class="max-h-96 space-y-1 overflow-y-auto">
                @forelse($this->selectablePlans as $plan)
                    <button
                        type="button"
                        wire:key="new-plan-{{ $plan->id }}"
                        wire:click="createFromPlan({{ $plan->id }})"
                        class="flex w-full items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-2 text-left hover:border-blue-500 dark:border-zinc-700"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">
                                {{ $plan->celebration_name ?: __('Untitled plan') }}
                            </span>
                            @if($plan->actual_date)
                                <span class="block text-xs text-zinc-500">
                                    {{ $plan->actual_date->translatedFormat('Y. F j.') }}
                                </span>
                            @endif
                        </span>
                        <flux:icon name="chevron-right" variant="micro" class="shrink-0 text-zinc-400" />
                    </button>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No music plans found.') }}</flux:text>
                @endforelse
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:button size="sm" variant="ghost" wire:click="createBlank">
                    {{ __('Start without a plan') }}
                </flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
