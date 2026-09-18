{{-- A score the deck has not taken: one click from being in it, and greyed
     until it is.

     An uploaded score holding several files is not one thing to take or leave:
     the melody sheet and the accompaniment are different music on the screen. So
     where there is a choice, the score only names itself and each file is added
     on its own line. --}}
@php
    $addedMusicId ??= null;
    $musicKey = $addedMusicId === null ? 'music-'.$assignmentId : 'added-'.$addedMusicId;
    $musicArgs = implode(', ', [$assignmentId ?? 'null', 'null', $addedMusicId ?? 'null']);
@endphp

<div class="text-zinc-500 dark:text-zinc-400" wire:key="offer-{{ $musicKey }}-{{ $score['id'] }}">
    <div class="flex items-center gap-2 py-0.5 ps-2 text-sm">
        @if($files === [])
            <flux:tooltip :content="__('Add to the projection')">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="plus"
                    :aria-label="__('Add to the projection')"
                    wire:click="toggleScore({{ $score['id'] }}, {{ $musicArgs }})"
                    class="shrink-0"
                    :disabled="! $score['in_booklets']"
                />
            </flux:tooltip>
        @endif

        <div class="min-w-0 flex-1">
            <span class="flex items-center gap-1.5">
                <flux:icon name="file-music" variant="micro" class="shrink-0 text-zinc-400" />
                @if($score['url'])
                    <a href="{{ $score['url'] }}" target="_blank" class="min-w-0 truncate hover:underline">{{ $score['title'] }}</a>
                @else
                    <span class="min-w-0 truncate">{{ $score['title'] }}</span>
                @endif
            </span>
            @if($score['incipit_url'])
                <x-incipit-image
                    :src="$score['incipit_url']"
                    :alt="__('Incipit').' — '.$score['title']"
                    class="mt-1 max-w-full"
                    imgClass="max-h-16 max-w-full rounded bg-white object-contain"
                />
            @endif
        </div>

        {{-- The same look a row of the deck offers, on a score it has not taken
             yet: what a score actually is, before deciding to put it in. --}}
        <flux:tooltip :content="__('Preview the score')">
            <flux:button
                size="sm"
                variant="ghost"
                icon="eye"
                data-offer-preview
                class="shrink-0"
                :aria-label="__('Preview the score')"
                wire:click="previewScore({{ $score['id'] }})"
            />
        </flux:tooltip>

        <flux:badge size="sm" color="zinc">{{ $score['format'] }}</flux:badge>

        @if(! $score['is_own'])
            <flux:tooltip :content="$score['owner_name']">
                <flux:icon name="user" variant="micro" class="shrink-0 text-zinc-400" />
            </flux:tooltip>
        @endif
    </div>

    @foreach($files as $file)
        <div class="flex items-center gap-2 py-0.5 ps-4 text-sm" wire:key="offer-file-{{ $musicKey }}-{{ $file['id'] }}">
            <flux:tooltip :content="__('Add this file to the projection')">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="plus"
                    :aria-label="__('Add this file to the projection')"
                    wire:click="toggleScore({{ $score['id'] }}, {{ $assignmentId ?? 'null' }}, {{ $file['id'] }}, {{ $addedMusicId ?? 'null' }})"
                    class="shrink-0"
                />
            </flux:tooltip>
            <span class="min-w-0 flex-1 truncate">{{ $file['name'] }}</span>

            <flux:tooltip :content="__('Preview this file')">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="eye"
                    data-offer-preview
                    class="shrink-0"
                    :aria-label="__('Preview this file')"
                    wire:click="previewScore({{ $score['id'] }}, {{ $file['id'] }})"
                />
            </flux:tooltip>
        </div>
    @endforeach
</div>
