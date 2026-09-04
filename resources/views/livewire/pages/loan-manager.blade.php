<div class="py-8">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6">
                <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('loans')" wire:navigate class="mb-3">
                    {{ __('Loans') }}
                </flux:button>

                <flux:heading size="2xl">{{ __('What this loan opens') }}</flux:heading>
                <flux:subheading>
                    {{ __('Everything is lent by default, and a score added later is lent too. Untick what should stay behind.') }}
                </flux:subheading>
            </div>

            @if($this->candidates->isEmpty())
                <flux:callout variant="secondary" icon="folder-open" class="border-dashed">
                    <flux:callout.heading>{{ __('This loan opens nothing yet') }}</flux:callout.heading>
                </flux:callout>
            @else
                <div class="space-y-2">
                    @foreach($this->candidates as $score)
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700"
                            wire:key="loan-score-{{ $score->id }}">
                            <flux:checkbox
                                :checked="! in_array($score->id, $excluded, true)"
                                wire:click="toggle({{ $score->id }})" />

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-medium">{{ $score->title }}</span>
                                    <x-score-format-badge :format="$score->format" />

                                    @if($this->isPassedOn($score))
                                        <flux:badge size="sm" color="amber" icon="arrow-path-rounded-square">
                                            {{ __('On loan · you are passing it on') }}
                                        </flux:badge>
                                    @endif

                                    @if($this->isNew($score))
                                        <flux:badge size="sm" color="blue">
                                            {{ __('Joined :date', ['date' => $score->created_at?->translatedFormat('Y-m-d')]) }}
                                        </flux:badge>
                                    @endif
                                </div>

                                @if($score->music)
                                    <div class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $score->music->title }}</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Taking a score out closes it for everyone holding this link.') }}
                    </flux:text>
                    <flux:button size="sm" variant="ghost" icon="check" wire:click="markReviewed">
                        {{ __('Mark as reviewed') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </div>
</div>
