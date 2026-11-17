<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('Season planner') }}</flux:heading>
                    <flux:subheading>{{ __('The dates your choir sings at, what each one needs, and who is coming — shared with the choir by a single link.') }}</flux:subheading>
                </div>

                <flux:modal.trigger name="new-season">
                    <flux:button variant="primary" icon="plus">{{ __('New season') }}</flux:button>
                </flux:modal.trigger>
            </div>

            @if($this->seasons->isEmpty())
                <flux:callout variant="secondary" icon="calendar-days" class="border-dashed">
                    <flux:callout.heading>{{ __('No seasons yet') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Start one, add its dates, and send the link to the choir so everyone can say which dates they are coming to.') }}</flux:callout.text>
                </flux:callout>
            @else
                <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach($this->seasons as $season)
                        @php($next = $this->nextEvents->get($season->id))
                        <a
                            href="{{ route('seasons.edit', ['season' => $season->id]) }}"
                            wire:navigate
                            wire:key="season-{{ $season->id }}"
                            class="flex flex-col gap-1 px-4 py-3 hover:bg-zinc-50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-zinc-800/50"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium">{{ $season->name }}</span>
                                    @if($season->loans->isNotEmpty())
                                        <flux:badge size="sm" color="green" icon="link">{{ __('Shared by link') }}</flux:badge>
                                    @endif
                                </div>
                                <span class="text-xs text-zinc-500">
                                    {{ trans_choice(':count date|:count dates', $season->events_count) }}
                                    · {{ trans_choice(':count member|:count members', $season->members_count) }}
                                </span>
                            </div>

                            @if($next)
                                <span class="text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ __('Next') }}: {{ $next->date->translatedFormat('F j.') }} – {{ $next->name }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    <flux:modal name="new-season" class="w-full max-w-md">
        <form wire:submit="create" class="space-y-4">
            <flux:heading size="lg">{{ __('New season') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" placeholder="Ferences Schola 2026-2027" autofocus required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
