<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ $score ? __('Edit Score') : __('Create Score') }}</flux:heading>
                    <flux:subheading>{{ __('Scores are always private and visible only to you.') }}</flux:subheading>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" icon="arrow-left" :href="route('scores')" wire:navigate>
                        {{ __('Back to Scores') }}
                    </flux:button>
                    @if($score)
                        <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Are you sure you want to delete this score?') }}">
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="mb-4 flex justify-end">
                <x-action-message on="score-created">
                    {{ __('Score created.') }}
                </x-action-message>
                <x-action-message on="score-updated">
                    {{ __('Score updated.') }}
                </x-action-message>
            </div>

            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:field required>
                        <flux:label>{{ __('Title') }}</flux:label>
                        <flux:input wire:model="title" :placeholder="__('Score title')" autofocus />
                        <flux:error name="title" />
                    </flux:field>

                    <flux:field required>
                        <flux:label>{{ __('Format') }}</flux:label>
                        <flux:select wire:model="format">
                            @foreach($formats as $formatOption)
                                <flux:select.option value="{{ $formatOption->value }}">{{ $formatOption->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="format" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_2fr]">
                    <flux:field>
                        <flux:label>{{ __('Search Music') }}</flux:label>
                        <flux:input type="search" wire:model.live.debounce.500ms="musicSearch" icon="magnifying-glass" :placeholder="__('Search visible music')" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Attached Music') }}</flux:label>
                        <flux:select wire:model="musicId">
                            <flux:select.option value="">{{ __('Not attached') }}</flux:select.option>
                            @foreach($musicOptions as $music)
                                <flux:select.option value="{{ $music->id }}">{{ $music->title }}{{ $music->subtitle ? ' — '.$music->subtitle : '' }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="musicId" />
                    </flux:field>
                </div>

                <flux:field required>
                    <flux:label>{{ __('Score Content') }}</flux:label>
                    <flux:textarea wire:model="content" rows="24" class="font-mono text-sm" :placeholder="__('Paste or type your ABC or GABC source here')" />
                    <flux:error name="content" />
                </flux:field>

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" :href="route('scores')" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" icon="pencil" wire:click="save">
                        {{ __('Save Score') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</div>
