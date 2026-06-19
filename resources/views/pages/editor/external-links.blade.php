<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

    <div class="mb-8">
        <flux:heading size="2xl">{{ __('External Links') }}</flux:heading>
        <flux:subheading>{{ __('Manage the external links shown at the bottom of the public pages') }}</flux:subheading>
    </div>

    <div class="space-y-6">
        <div class="flex justify-end">
            <flux:button variant="primary" icon="plus" wire:click="create">
                {{ __('Add Link') }}
            </flux:button>
        </div>

        @if ($links->isEmpty())
            <flux:text class="text-zinc-500">{{ __('No external links yet.') }}</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($links as $link)
                    <div wire:key="link-{{ $link->id }}" class="flex flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg">{{ $link->title }}</flux:heading>
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="mt-1 text-sm text-accent hover:underline break-all">
                            {{ $link->url }}
                        </a>
                        <p class="mt-2 grow text-sm text-zinc-600 dark:text-zinc-400">{{ $link->description }}</p>
                        <div class="mt-4 flex items-center justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $link->id }})">
                                {{ __('Edit') }}
                            </flux:button>
                            <flux:button
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="delete({{ $link->id }})"
                                wire:confirm="{{ __('Are you sure you want to delete this link?') }}"
                            >
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal wire:model="showModal" max-width="lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit External Link') : __('Add External Link') }}
            </flux:heading>

            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="title" autofocus autocomplete="off" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="description" rows="4" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('URL') }}</flux:label>
                <flux:input wire:model="url" type="url" placeholder="https://..." />
                <flux:error name="url" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Sort order') }}</flux:label>
                <flux:input wire:model="sortOrder" type="number" min="0" />
                <flux:description>{{ __('Lower numbers appear first.') }}</flux:description>
                <flux:error name="sortOrder" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ $editingId ? __('Save') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
