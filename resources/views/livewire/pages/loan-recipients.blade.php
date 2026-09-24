<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6">
                <flux:button size="sm" variant="ghost" icon="arrow-left" :href="route('loans', ['tab' => 'lent'])" wire:navigate class="mb-3">
                    {{ __('Loans') }}
                </flux:button>

                <flux:heading size="2xl">{{ __('Who can open this loan') }}</flux:heading>
                <flux:subheading>
                    {{ __('By default anyone holding the link can open it. Restrict it, and only the people listed here can — after signing in.') }}
                </flux:subheading>
            </div>

            <flux:switch wire:model.live="restricted" :label="__('Only the people listed')" />

            @if($restricted)
                <div class="mt-6 space-y-6">
                    <form wire:submit="addByEmail" class="flex items-start gap-2">
                        <div class="flex-1">
                            <flux:input
                                type="email"
                                wire:model="email"
                                :placeholder="__('Email address they registered with')"
                                icon="envelope" />
                            <flux:error name="email" />
                        </div>
                        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
                    </form>

                    <div>
                        <flux:heading size="sm" class="mb-2">{{ __('Can open it') }}</flux:heading>

                        @forelse($this->recipients as $recipient)
                            <div
                                class="flex items-center justify-between gap-3 border-b border-zinc-200 py-2 last:border-b-0 dark:border-zinc-700"
                                wire:key="loan-recipient-{{ $recipient->id }}">
                                <span class="text-sm">{{ $recipient->displayName }}</span>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="remove({{ $recipient->id }})">
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>
                        @empty
                            <flux:text class="text-sm">
                                {{ __('Nobody yet. Until you add someone, only you can open this loan.') }}
                            </flux:text>
                        @endforelse
                    </div>

                    @if($this->suggestions->isNotEmpty())
                        <div>
                            <flux:heading size="sm" class="mb-2">{{ __('People who opened your loans') }}</flux:heading>
                            <div class="flex flex-wrap gap-2">
                                @foreach($this->suggestions as $suggestion)
                                    <flux:button
                                        size="xs"
                                        variant="outline"
                                        icon="plus"
                                        wire:key="loan-suggestion-{{ $suggestion->id }}"
                                        wire:click="add({{ $suggestion->id }})">
                                        {{ $suggestion->displayName }}
                                    </flux:button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('The people listed cannot pass it on: what this loan opens does not travel in their own folders, plans or booklets.') }}
                    </flux:text>
                </div>
            @endif
        </flux:card>
    </div>
</div>
