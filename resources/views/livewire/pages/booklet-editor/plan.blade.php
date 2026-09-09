{{-- The booklet, read as the service it was made from.

     One list rather than two. The plan is the list — slots, their music, the
     engravings each music can be sung from — and what the booklet took from it
     stands in place, in the booklet's own order, with everything that can be
     done to it. What it did not take waits underneath, greyed, one click from
     being taken. So the question the old two lists could not answer between them
     — which of the plan's music is in the booklet — is answered by looking. --}}
<flux:card class="p-4">
    <div class="mb-3 flex items-center justify-between gap-2">
        <flux:heading size="lg">
            {{ $booklet->musicPlan ? __('The plan') : __('In this booklet') }}
        </flux:heading>

        <div class="flex shrink-0 items-center gap-1">
            {{-- The way back to the service itself. What the booklet can do to the
                 plan is reorder it; adding a music to a slot, or changing which one
                 is sung, is the plan's own business, so it is one click away and
                 opens in a tab of its own — the booklet is left standing where it
                 was, with everything already laid out. --}}
            @if($booklet->musicPlan)
                <flux:tooltip :content="__('Open the music plan in a new tab')">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="arrow-top-right-on-square"
                        href="{{ auth()->user()?->can('update', $booklet->musicPlan)
                            ? route('music-plan-editor', $booklet->musicPlan)
                            : route('music-plan-view', $booklet->musicPlan) }}"
                        target="_blank"
                    >
                        {{ __('Open the plan') }}
                    </flux:button>
                </flux:tooltip>
            @endif

            {{-- Words before the first slot: what the booklet says before the service
                 begins. Everything else is written into the plan itself, from the
                 slot, the music or the score it belongs under. --}}
            <flux:tooltip :content="__('Add text at the very top of the booklet')">
                <flux:button size="sm" variant="ghost" icon="message-square-plus" wire:click="addText">
                    {{ __('Add text') }}
                </flux:button>
            </flux:tooltip>
        </div>
    </div>

    @if($this->outline === [])
        <flux:text class="text-sm text-zinc-500">
            {{ $booklet->musicPlan
                ? __('This plan has no slots yet.')
                : __('This booklet is not linked to a music plan.') }}
        </flux:text>
    @endif

    <ul class="booklet-plan space-y-1.5">
        @foreach($this->outline as $node)
            @if($node['kind'] === 'entry')
                @include('livewire.pages.booklet-editor.entry', ['entry' => $node['entry']])
            @else
                @php $slotChosen = $node['weight'] > 0; @endphp
                <li
                    wire:key="slot-{{ $node['id'] }}"
                    data-plan-slot="{{ $node['id'] }}"
                    class="rounded-md {{ $slotChosen ? 'border-s-2 border-green-500 ps-2 dark:border-green-500' : 'ps-2.5' }}"
                >
                    {{-- The slot's name is the one heading in the pane, so it is
                         given a band of its own across the width: what belongs to
                         which part of the service can then be told apart at a
                         glance, without reading. --}}
                    <div class="flex items-center gap-1.5 rounded-md px-2 py-1 {{ $slotChosen ? 'bg-green-50 dark:bg-green-950/40' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                        <div class="flex min-w-0 flex-1 items-center gap-1 text-xs font-semibold uppercase tracking-wide {{ $slotChosen ? 'text-zinc-700 dark:text-zinc-200' : 'text-zinc-400 dark:text-zinc-500' }}">
                            <span class="min-w-0 truncate">{{ $node['name'] }}</span>

                            {{-- The switch for the slot's name sits beside the name
                                 itself. The name is printed unless it is turned off
                                 here, and it is spoken by one row — whichever opens
                                 the slot — so that is the row the choice is kept on. --}}
                            @if($slotChosen)
                                <flux:tooltip :content="__('Print the slot name')">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :icon="$node['showsName'] ? 'eye' : 'eye-slash'"
                                        wire:click="toggleSlotName({{ $node['headingEntryId'] }})"
                                        aria-pressed="{{ $node['showsName'] ? 'true' : 'false' }}"
                                        class="shrink-0 {{ $node['showsName'] ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                        :aria-label="__('Print the slot name')"
                                    />
                                </flux:tooltip>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-0.5">
                            {{-- Named for where the words come out, not for what
                                 they are filed under: this is the first thing the
                                 slot says, printed straight beneath its name. --}}
                            <flux:tooltip :content="__('Add text under the slot name')">
                                <flux:button size="sm" variant="ghost" icon="message-square-plus" :aria-label="__('Add text under the slot name')" wire:click="addText({{ $node['id'] }})" />
                            </flux:tooltip>
                            @if($node['canMoveUp'] || $node['canMoveDown'])
                                <flux:tooltip :content="__('Move this slot up')">
                                    <flux:button size="sm" variant="ghost" icon="chevron-up" :aria-label="__('Move this slot up')" :disabled="! $node['canMoveUp']" wire:click="moveSlot({{ $node['id'] }}, -1)" />
                                </flux:tooltip>
                                <flux:tooltip :content="__('Move this slot down')">
                                    <flux:button size="sm" variant="ghost" icon="chevron-down" :aria-label="__('Move this slot down')" :disabled="! $node['canMoveDown']" wire:click="moveSlot({{ $node['id'] }}, 1)" />
                                </flux:tooltip>
                            @endif
                        </div>
                    </div>

                    <ul class="mt-1 ms-1 space-y-1.5">
                        @foreach($node['children'] as $child)
                            @if($child['kind'] === 'entry')
                                @include('livewire.pages.booklet-editor.entry', ['entry' => $child['entry']])
                            @else
                                @php $musicChosen = $child['weight'] > 0; @endphp
                                <li wire:key="music-{{ $child['id'] }}" data-plan-music="{{ $child['id'] }}">
                                    <div class="flex items-center gap-1.5 text-sm">
                                        <flux:icon name="music" variant="micro" class="shrink-0 {{ $musicChosen ? 'text-green-600 dark:text-green-400' : 'text-indigo-400' }}" />

                                        <div class="flex min-w-0 flex-1 items-center gap-1 {{ $musicChosen ? 'font-medium' : 'text-zinc-500 dark:text-zinc-400' }}">
                                            <span class="min-w-0 truncate">
                                                @if($child['musicId'])
                                                    <a href="{{ route('music-view', $child['musicId']) }}" target="_blank" class="hover:underline">{{ $child['title'] }}</a>
                                                @else
                                                    {{ $child['title'] }}
                                                @endif
                                            </span>

                                            {{-- The music's own name is printed above it
                                                 where a slot holds several, beside the slot
                                                 where it holds one. Either way this is the
                                                 switch that keeps it off the page, next to
                                                 the name it governs. --}}
                                            @if($musicChosen)
                                                <flux:tooltip :content="__('Print the music title')">
                                                    <flux:button
                                                        size="sm"
                                                        variant="ghost"
                                                        :icon="$child['showsName'] ? 'eye' : 'eye-slash'"
                                                        wire:click="toggleMusicName({{ $child['headingEntryId'] }})"
                                                        aria-pressed="{{ $child['showsName'] ? 'true' : 'false' }}"
                                                        class="shrink-0 {{ $child['showsName'] ? '!text-blue-600 dark:!text-blue-400' : '' }}"
                                                        :aria-label="__('Print the music title')"
                                                    />
                                                </flux:tooltip>
                                            @endif

                                            {{-- Where the music is to be found in the books the
                                                 congregation already holds, said as briefly as it
                                                 can be.

                                                 No third eye beside the other two: the reference
                                                 itself is the switch, and it is drawn as it will
                                                 be printed — solid once the booklet says it,
                                                 outlined and pale while it does not. So there is
                                                 nothing to read to know what will happen, and
                                                 nothing to tell apart from the eyes that govern
                                                 the names. --}}
                                            @if($child['reference'])
                                                @if($musicChosen)
                                                    <flux:tooltip :content="$child['showsReference']
                                                        ? __('Printed in the booklet — click to leave it out')
                                                        : __('Not printed — click to put it in the booklet')">
                                                        <button
                                                            type="button"
                                                            data-plan-music-reference
                                                            wire:click="toggleMusicCollections({{ $child['headingEntryId'] }})"
                                                            aria-pressed="{{ $child['showsReference'] ? 'true' : 'false' }}"
                                                            class="shrink-0 cursor-pointer rounded-md border px-1.5 py-0.5 text-xs font-normal transition
                                                                {{ $child['showsReference']
                                                                    ? 'border-blue-600 bg-blue-600/10 text-blue-700 dark:border-blue-400 dark:bg-blue-400/15 dark:text-blue-300'
                                                                    : 'border-dashed border-zinc-300 text-zinc-400 hover:border-zinc-400 hover:text-zinc-600 dark:border-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300' }}"
                                                        >{{ $child['reference'] }}</button>
                                                    </flux:tooltip>
                                                @else
                                                    {{-- Nothing of this music is in the booklet, so
                                                         there is no row to keep the answer on: the
                                                         reference is only told, not offered. --}}
                                                    <span data-plan-music-reference class="shrink-0 text-xs font-normal text-zinc-400 dark:text-zinc-500">{{ $child['reference'] }}</span>
                                                @endif
                                            @endif
                                        </div>

                                        <div class="flex shrink-0 items-center gap-0.5">
                                            <flux:tooltip :content="__('Add text under the music name')">
                                                <flux:button size="sm" variant="ghost" icon="message-square-plus" :aria-label="__('Add text under the music name')" wire:click="addText(null, {{ $child['id'] }})" />
                                            </flux:tooltip>
                                            @if($child['canMoveUp'] || $child['canMoveDown'])
                                                <flux:tooltip :content="__('Move this music up')">
                                                    <flux:button size="sm" variant="ghost" icon="chevron-up" :aria-label="__('Move this music up')" :disabled="! $child['canMoveUp']" wire:click="moveMusic({{ $child['id'] }}, -1)" />
                                                </flux:tooltip>
                                                <flux:tooltip :content="__('Move this music down')">
                                                    <flux:button size="sm" variant="ghost" icon="chevron-down" :aria-label="__('Move this music down')" :disabled="! $child['canMoveDown']" wire:click="moveMusic({{ $child['id'] }}, 1)" />
                                                </flux:tooltip>
                                            @endif
                                        </div>
                                    </div>

                                    @if($child['children'] !== [])
                                        <ul class="mt-1 ms-3 space-y-1.5">
                                            @foreach($child['children'] as $grandchild)
                                                @include('livewire.pages.booklet-editor.entry', ['entry' => $grandchild['entry']])
                                            @endforeach
                                        </ul>
                                    @endif

                                    @if($child['offers'] !== [])
                                        <div class="mt-0.5 ms-3">
                                            @foreach($child['offers'] as $offer)
                                                @include('livewire.pages.booklet-editor.offer', [
                                                    'score' => $offer['score'],
                                                    'files' => $offer['files'],
                                                    'assignmentId' => $child['id'],
                                                ])
                                            @endforeach
                                        </div>
                                    @elseif($child['children'] === [])
                                        <div class="ps-2 text-xs text-zinc-400">{{ __('No scores available') }}</div>
                                    @endif
                                </li>
                            @endif
                        @endforeach

                        @if($node['children'] === [])
                            <li class="ps-2 text-xs text-zinc-400">{{ __('No music planned here') }}</li>
                        @endif
                    </ul>
                </li>
            @endif
        @endforeach
    </ul>
</flux:card>
