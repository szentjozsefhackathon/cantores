<div class="py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <flux:card class="p-4 lg:p-6">
            <div class="mb-6">
                <flux:heading size="2xl">{{ __('My Secret Links') }}</flux:heading>
                <flux:subheading>{{ __('Anyone with one of these links can open what it points at, without signing in. Revoking a link takes it back immediately.') }}</flux:subheading>
            </div>

            @if($shares->isEmpty())
                <flux:callout variant="secondary" icon="link" class="border-dashed">
                    <flux:callout.heading>{{ __('No active secret links') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Share a score, folder or music plan to create one.') }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:callout variant="secondary" icon="information-circle" class="mb-4">
                    <flux:callout.text>{{ __('A folder or music plan link also opens the scores it contains. Revoking it closes those too.') }}</flux:callout.text>
                </flux:callout>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Shared') }}</flux:table.column>
                        <flux:table.column class="hidden sm:table-cell">{{ __('Type') }}</flux:table.column>
                        <flux:table.column class="hidden md:table-cell">{{ __('Created') }}</flux:table.column>
                        <flux:table.column class="hidden md:table-cell">{{ __('Last opened') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach($shares as $share)
                            @php($described = $this->describe($share))
                            <flux:table.row wire:key="share-row-{{ $share->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">
                                        @if($described['url'])
                                            <a href="{{ $described['url'] }}" wire:navigate class="hover:underline">{{ $described['title'] }}</a>
                                        @else
                                            {{ $described['title'] }}
                                        @endif
                                    </div>
                                    <div class="mt-0.5 truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $this->linkFor($share) }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="hidden sm:table-cell">
                                    <flux:badge color="zinc" size="sm">{{ $described['type'] }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="hidden md:table-cell">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $share->created_at?->translatedFormat('Y-m-d') }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell class="hidden md:table-cell">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $share->last_viewed_at?->translatedFormat('Y-m-d H:i') ?? __('Never') }}
                                    </span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="revoke({{ $share->id }})"
                                        wire:confirm="{{ __('Revoke this link? Anyone still holding it will lose access.') }}">
                                        {{ __('Revoke') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if($shares->hasPages())
                    <div class="mt-4">
                        {{ $shares->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>
</div>
