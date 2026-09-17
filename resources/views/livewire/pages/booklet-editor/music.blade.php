{{-- One music of the booklet: the plan's, or one only this booklet holds.

     A music the plan does not have is the one thing in the pane that is not the
     service, so it is marked as such — an amber band down its side and a badge
     saying so — and it is the only music here that can be removed: the plan's
     own music belongs to the plan. --}}
@php
    $musicChosen = $music['weight'] > 0;
    $local = $music['local'] ?? false;
    $moveCall = $local ? 'moveAddedMusic' : 'moveMusic';
    $textArgs = $local ? "null, null, null, {$music['id']}" : "null, {$music['id']}";
@endphp

<li
    wire:key="{{ $music['key'] }}"
    data-plan-music="{{ $music['id'] }}"
    @if($local)
        data-plan-added-music="{{ $music['id'] }}"
        class="rounded-md border-s-2 border-dashed border-amber-500 ps-2"
        @if($this->openedAddedMusicId === $music['id'])
            x-init="$el.scrollIntoView({ block: 'nearest', behavior: 'smooth' })"
        @endif
    @endif
>
    <div class="flex items-center gap-1.5 text-sm">
        <flux:icon name="music" variant="micro" class="shrink-0 {{ $local ? 'text-amber-500' : ($musicChosen ? 'text-green-600 dark:text-green-400' : 'text-indigo-400') }}" />

        <div class="flex min-w-0 flex-1 items-center gap-1 {{ $musicChosen ? 'font-medium' : 'text-zinc-500 dark:text-zinc-400' }}">
            <span class="min-w-0 truncate">
                @if($music['musicId'])
                    <a href="{{ route('music-view', $music['musicId']) }}" target="_blank" class="hover:underline">{{ $music['title'] }}</a>
                @else
                    {{ $music['title'] }}
                @endif
            </span>

            {{-- The music's own name is printed above it where a slot holds several,
                 beside the slot where it holds one. Either way this is the switch
                 that keeps it off the page, next to the name it governs. --}}
            @if($musicChosen)
                <flux:tooltip :content="__('Print the music title')">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        :icon="$music['showsName'] ? 'eye' : 'eye-slash'"
                        wire:click="toggleMusicName({{ $music['headingEntryId'] }})"
                        aria-pressed="{{ $music['showsName'] ? 'true' : 'false' }}"
                        class="shrink-0 {{ $music['showsName'] ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                        :aria-label="__('Print the music title')"
                    />
                </flux:tooltip>
            @endif

            {{-- Where the music is to be found in the books the congregation
                 already holds, said as briefly as it can be.

                 No third eye beside the other two: the reference itself is the
                 switch, and it is drawn as it will be printed — solid once the
                 booklet says it, outlined and pale while it does not. --}}
            @if($music['reference'])
                @if($musicChosen)
                    <flux:tooltip :content="$music['showsReference']
                        ? __('Printed in the booklet — click to leave it out')
                        : __('Not printed — click to put it in the booklet')">
                        <button
                            type="button"
                            data-plan-music-reference
                            wire:click="toggleMusicCollections({{ $music['headingEntryId'] }})"
                            aria-pressed="{{ $music['showsReference'] ? 'true' : 'false' }}"
                            class="shrink-0 cursor-pointer rounded-md border px-1.5 py-0.5 text-xs font-normal transition
                                {{ $music['showsReference']
                                    ? 'border-blue-600 bg-blue-600/10 text-blue-700 dark:border-blue-400 dark:bg-blue-400/15 dark:text-blue-300'
                                    : 'border-dashed border-zinc-300 text-zinc-400 hover:border-zinc-400 hover:text-zinc-600 dark:border-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300' }}"
                        >{{ $music['reference'] }}</button>
                    </flux:tooltip>
                @else
                    {{-- Nothing of this music is in the booklet, so there is no row
                         to keep the answer on: the reference is only told. --}}
                    <span data-plan-music-reference class="shrink-0 text-xs font-normal text-zinc-400 dark:text-zinc-500">{{ $music['reference'] }}</span>
                @endif
            @endif

            {{-- Said last, after everything the title bar switches on and off. --}}
            @if($local)
                <flux:badge size="sm" color="amber" class="shrink-0">{{ __('Only in this booklet') }}</flux:badge>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-0.5">
            <flux:tooltip :content="__('Add text under the music name')">
                <flux:button size="sm" variant="ghost" icon="message-square-plus" :aria-label="__('Add text under the music name')" wire:click="addText({{ $textArgs }})" />
            </flux:tooltip>
            @if($music['canMoveUp'] || $music['canMoveDown'])
                <flux:tooltip :content="__('Move this music up')">
                    <flux:button size="sm" variant="ghost" icon="chevron-up" :aria-label="__('Move this music up')" :disabled="! $music['canMoveUp']" wire:click="{{ $moveCall }}({{ $music['id'] }}, -1)" />
                </flux:tooltip>
                <flux:tooltip :content="__('Move this music down')">
                    <flux:button size="sm" variant="ghost" icon="chevron-down" :aria-label="__('Move this music down')" :disabled="! $music['canMoveDown']" wire:click="{{ $moveCall }}({{ $music['id'] }}, 1)" />
                </flux:tooltip>
            @endif
            @if($local)
                {{-- Asked first only when there is something to lose: an empty
                     music was one click to add and is one click to take away. --}}
                @if($musicChosen)
                    <flux:modal.trigger :name="'remove-added-music-'.$music['id']">
                        <flux:tooltip :content="__('Remove this music from the booklet')">
                            <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Remove this music from the booklet')" />
                        </flux:tooltip>
                    </flux:modal.trigger>

                    <flux:modal :name="'remove-added-music-'.$music['id']" class="min-w-[22rem]">
                        <div class="space-y-6">
                            <div>
                                <flux:heading size="lg">{{ __('Remove this music from the booklet') }}</flux:heading>
                                <flux:text class="mt-2">{{ __('The scores chosen from it are removed with it.') }}</flux:text>
                            </div>
                            <div class="flex gap-2">
                                <flux:spacer />
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button variant="danger" wire:click="removeAddedMusic({{ $music['id'] }})">{{ __('Remove') }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @else
                    <flux:tooltip :content="__('Remove this music from the booklet')">
                        <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Remove this music from the booklet')" wire:click="removeAddedMusic({{ $music['id'] }})" />
                    </flux:tooltip>
                @endif
            @endif
        </div>
    </div>

    @if($music['children'] !== [])
        <ul class="mt-1 ms-3 space-y-1.5">
            @foreach($music['children'] as $grandchild)
                @include('livewire.pages.booklet-editor.entry', ['entry' => $grandchild['entry']])
            @endforeach
        </ul>
    @endif

    @if($music['offers'] !== [])
        <div class="mt-0.5 ms-3">
            @foreach($music['offers'] as $offer)
                @include('livewire.pages.booklet-editor.offer', [
                    'score' => $offer['score'],
                    'files' => $offer['files'],
                    'assignmentId' => $local ? null : $music['id'],
                    'addedMusicId' => $local ? $music['id'] : null,
                ])
            @endforeach
        </div>
    @elseif($music['children'] === [])
        <div class="ps-2 text-xs text-zinc-400">{{ __('No scores available') }}</div>
    @endif
</li>
