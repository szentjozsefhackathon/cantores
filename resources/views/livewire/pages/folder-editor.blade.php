<div class="py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">

            {{-- Header --}}
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <flux:button variant="ghost" icon="arrow-left" :href="route('folders')" wire:navigate size="sm" />
                    <div>
                        <flux:heading size="2xl">
                            {{ $folder ? __('Edit Folder') : __('Create Folder') }}
                        </flux:heading>
                    </div>
                </div>

                <flux:button variant="primary" icon="check" wire:click="save">
                    {{ __('Save') }}
                </flux:button>
            </div>

            {{-- Name --}}
            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" :placeholder="__('Folder name')" autofocus />
                <flux:error name="name" />
            </flux:field>

            @if($folder)
            {{-- Score Assignment --}}
            <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700"
                 x-data="{ search: '' }">
                <flux:heading size="sm" class="mb-3">{{ __('Scores') }}</flux:heading>

                @if($this->userScores->isNotEmpty())
                    <flux:input
                        x-model="search"
                        type="search"
                        :placeholder="__('Filter scores…')"
                        size="sm"
                        class="mb-3" />

                    <div class="max-h-64 space-y-1 overflow-y-auto">
                        @foreach($this->userScores as $score)
                        <div x-show="search === '' || '{{ strtolower($score->title) }}'.includes(search.toLowerCase())">
                            <flux:checkbox
                                wire:key="score-check-{{ $score->id }}"
                                wire:click="toggleScore({{ $score->id }})"
                                :checked="in_array($score->id, $scoreIds)"
                                :label="$score->title" />
                        </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="secondary" icon="book-open-text" class="border-dashed">
                        <flux:callout.heading>{{ __('No scores yet') }}</flux:callout.heading>
                        <flux:callout.text>
                            <a href="{{ route('scores.create') }}" wire:navigate class="underline">{{ __('Create a score') }}</a>
                            {{ __('first to assign it to this folder.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            {{-- Secret Link --}}
            <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700"
                 x-data="{ secretLinkCopied: false }">
                <div class="flex items-center justify-between gap-2">
                    <flux:subheading class="font-medium">{{ __('Secret Link') }}</flux:subheading>
                    <div class="flex min-w-0 flex-1 items-center gap-2" x-show="$wire.secretLinkUrl" x-cloak>
                        <flux:input readonly x-bind:value="$wire.secretLinkUrl ?? ''" class="min-w-0 flex-1 font-mono text-sm" />
                        <flux:button
                            icon="clipboard"
                            variant="ghost"
                            :title="__('Copy link')"
                            x-on:click="navigator.clipboard.writeText($wire.secretLinkUrl).then(() => { secretLinkCopied = true; setTimeout(() => secretLinkCopied = false, 2000) })"
                            x-bind:class="secretLinkCopied ? 'text-green-600' : ''" />
                        <flux:button
                            icon="trash"
                            variant="ghost"
                            :title="__('Delete link')"
                            wire:click="deleteSecretLink"
                            wire:confirm="{{ __('This will invalidate the current link. Are you sure?') }}" />
                    </div>
                    <div x-show="!$wire.secretLinkUrl">
                        <flux:button icon="link" variant="ghost" wire:click="generateSecretLink">
                            {{ __('Generate Secret Link') }}
                        </flux:button>
                    </div>
                </div>
                <flux:text class="mt-1 text-xs text-zinc-500" x-show="$wire.secretLinkUrl" x-cloak>
                    {{ __('Anyone with this link can view the folder contents (read-only). Delete the link to revoke access.') }}
                </flux:text>
                <flux:text class="mt-1 text-xs text-zinc-500" x-show="!$wire.secretLinkUrl">
                    {{ __('Generate a secret link to share this folder as a read-only preview.') }}
                </flux:text>
            </div>

            {{-- Delete --}}
            <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:button variant="danger" icon="trash" wire:click="delete" wire:confirm="{{ __('Are you sure you want to delete this folder?') }}">
                    {{ __('Delete Folder') }}
                </flux:button>
            </div>
            @endif

        </flux:card>
    </div>
</div>
