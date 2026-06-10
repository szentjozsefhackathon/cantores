<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="2xl">{{ __('My Folders') }}</flux:heading>
                    <flux:subheading>{{ __('Private score folders created by you.') }}</flux:subheading>
                </div>

                <flux:button variant="primary" icon="plus" :href="route('folders.create')" wire:navigate>
                    {{ __('Create Folder') }}
                </flux:button>
            </div>


            <div class="mb-6">
                <flux:field>
                    <flux:label>{{ __('Search') }}</flux:label>
                    <flux:input type="search" wire:model.live.debounce.500ms="search" icon="magnifying-glass" :placeholder="__('Search')" />
                </flux:field>
            </div>

            @if($folders->isEmpty())
                <flux:callout variant="secondary" icon="folder-open" class="border-dashed">
                    <flux:callout.heading>{{ __('No folders found') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Create your first folder to organise your scores.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Scores') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Updated') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($folders as $folder)
                            <flux:table.row wire:key="folder-row-{{ $folder->id }}">
                                <flux:table.cell>
                                    <div class="flex flex-wrap items-center gap-1.5 font-medium">
                                        <a href="{{ route('folders.edit', ['folder' => $folder->id]) }}" wire:navigate class="hover:underline">
                                            {{ $folder->name }}
                                        </a>
                                        @if($folder->share_token)
                                            <flux:icon name="link" size="sm" class="text-blue-500 dark:text-blue-400" :title="__('Secret link active')" />
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <flux:badge color="zinc" size="sm">{{ $folder->scores_count }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $folder->updated_at->translatedFormat('Y-m-d H:i') }}</span>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($folders->hasPages())
                    <div class="mt-4">
                        {{ $folders->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>
</div>
