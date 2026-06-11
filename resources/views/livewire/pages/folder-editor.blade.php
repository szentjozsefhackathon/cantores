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
            {{-- Score List --}}
            <div class="mt-6 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="sm">{{ __('Scores') }}</flux:heading>
                    <flux:button icon="plus" size="sm" wire:click="$set('showModal', true)">
                        {{ __('Add Scores') }}
                    </flux:button>
                </div>

                @if($addedScores->isEmpty())
                    <flux:callout variant="secondary" icon="book-open-text" class="border-dashed">
                        <flux:callout.heading>{{ __('No scores yet') }}</flux:callout.heading>
                        <flux:callout.text>{{ __('Click "Add Scores" to add scores to this folder.') }}</flux:callout.text>
                    </flux:callout>
                @else
                    <div class="space-y-2">
                        @foreach($addedScores as $score)
                        <div class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50"
                             wire:key="added-{{ $score->id }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <a href="{{ route('scores.edit', $score) }}" wire:navigate
                                       class="font-medium hover:underline">{{ $score->title }}</a>
                                    <x-score-format-badge :format="$score->format" />
                                </div>
                                @if($score->music)
                                <div class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $score->music->title }}</div>
                                @endif
                                @if($score->hasIncipit())
                                <div class="mt-2">
                                    <x-incipit-image
                                        :src="$score->incipitUrl()"
                                        :alt="$score->title"
                                        img-class="block h-14 w-auto" />
                                </div>
                                @endif
                            </div>
                            <flux:button
                                icon="x-mark"
                                variant="ghost"
                                size="sm"
                                wire:click="toggleScore({{ $score->id }})"
                                :title="__('Remove from folder')" />
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Score Picker Modal --}}
            <flux:modal wire:model="showModal" class="max-w-2xl">
                <flux:heading size="lg" class="mb-4">{{ __('Add Scores') }}</flux:heading>

                <flux:input
                    wire:model.live.debounce.300ms="modalSearch"
                    type="search"
                    :placeholder="__('Search scores…')"
                    icon="magnifying-glass"
                    class="mb-3" />

                <div class="max-h-96 space-y-2 overflow-y-auto">
                    @forelse($modalScores as $score)
                    <div class="flex items-start gap-3 rounded-lg border px-3 py-2 {{ in_array($score->id, $scoreIds) ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50' }}"
                         wire:key="modal-{{ $score->id }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-medium">{{ $score->title }}</span>
                                <x-score-format-badge :format="$score->format" />
                            </div>
                            @if($score->music)
                            <div class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $score->music->title }}</div>
                            @endif
                            @if($score->hasIncipit())
                            <div class="mt-2">
                                <x-incipit-image
                                    :src="$score->incipitUrl()"
                                    :alt="$score->title"
                                    img-class="block h-14 w-auto" />
                            </div>
                            @endif
                        </div>
                        @if(in_array($score->id, $scoreIds))
                        <flux:button
                            wire:click="toggleScore({{ $score->id }})"
                            size="sm"
                            variant="outline"
                            color="red"
                            icon="minus">
                            {{ __('Remove') }}
                        </flux:button>
                        @else
                        <flux:button
                            wire:click="toggleScore({{ $score->id }})"
                            size="sm"
                            variant="primary"
                            icon="plus">
                            {{ __('Add') }}
                        </flux:button>
                        @endif
                    </div>
                    @empty
                    <flux:callout variant="secondary" icon="book-open-text" class="border-dashed">
                        <flux:callout.heading>{{ __('No scores found') }}</flux:callout.heading>
                    </flux:callout>
                    @endforelse
                </div>

                @if($modalScores->hasPages())
                <div class="mt-4 flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <flux:button
                        wire:click="previousModalPage"
                        :disabled="$modalPage <= 1"
                        icon="chevron-left"
                        size="sm"
                        variant="ghost" />
                    <span class="text-sm text-zinc-500">{{ $modalPage }} / {{ $modalScores->lastPage() }}</span>
                    <flux:button
                        wire:click="nextModalPage({{ $modalScores->lastPage() }})"
                        :disabled="$modalPage >= $modalScores->lastPage()"
                        icon="chevron-right"
                        size="sm"
                        variant="ghost" />
                </div>
                @endif

                <div class="mt-4 flex justify-end">
                    <flux:button wire:click="$set('showModal', false)">{{ __('Done') }}</flux:button>
                </div>
            </flux:modal>

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
