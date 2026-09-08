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

        {{-- Words before the first slot: what the booklet says before the service
             begins. Everything else is written into the plan itself, from the
             slot, the music or the score it belongs under. --}}
        <flux:tooltip :content="__('Add text at the very top of the booklet')">
            <flux:button size="sm" variant="ghost" icon="message-square-plus" wire:click="addText">
                {{ __('Add text') }}
            </flux:button>
        </flux:tooltip>
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
                        <div class="min-w-0 flex-1 truncate text-xs font-semibold uppercase tracking-wide {{ $slotChosen ? 'text-zinc-700 dark:text-zinc-200' : 'text-zinc-400 dark:text-zinc-500' }}">
                            {{ $node['name'] }}
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

                                        <div class="min-w-0 flex-1 truncate {{ $musicChosen ? 'font-medium' : 'text-zinc-500 dark:text-zinc-400' }}">
                                            @if($child['musicId'])
                                                <a href="{{ route('music-view', $child['musicId']) }}" target="_blank" class="hover:underline">{{ $child['title'] }}</a>
                                            @else
                                                {{ $child['title'] }}
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
