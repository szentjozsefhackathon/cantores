<div class="inline">
    <flux:button variant="outline" color="emerald" icon="arrow-down-tray" wire:click="open">
        {{ __('Export as .dia') }}
    </flux:button>
    <flux:error name="plan" />

    <flux:modal wire:model.self="show" class="md:w-4xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Diatár export') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Review the source and slide order for every music. These changes apply only to this download.') }}
                </flux:text>
            </div>

            @if($catalogRevision === '')
                <flux:callout variant="danger" icon="exclamation-triangle">
                    {{ __('No published Diatár catalogue is available yet.') }}
                </flux:callout>
            @endif

            @if($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle">
                    {{ $errors->first() }}
                </flux:callout>
            @endif

            <form method="POST" action="{{ route('music-plans.diatar-export', $musicPlan) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="catalog_sync_run_id" value="{{ $catalogSyncRunId }}">
                <input type="hidden" name="catalog_revision" value="{{ $catalogRevision }}">

                @forelse($rows as $rowIndex => $row)
                    <flux:card wire:key="diatar-assignment-{{ $row['assignment_id'] }}" class="space-y-3 p-4">
                        <input type="hidden" name="rows[{{ $rowIndex }}][assignment_id]" value="{{ $row['assignment_id'] }}">
                        <input type="hidden" name="rows[{{ $rowIndex }}][song_id]" value="{{ $row['selected_song_id'] }}">
                        <input type="hidden" name="rows[{{ $rowIndex }}][omitted]" value="{{ $row['omitted'] ? 1 : 0 }}">
                        @foreach($row['selected_slide_ids'] as $slideId)
                            <input type="hidden" name="rows[{{ $rowIndex }}][slide_ids][]" value="{{ $slideId }}">
                        @endforeach
                        @if($row['selected_slide_ids'] === [])
                            <input type="hidden" name="rows[{{ $rowIndex }}][slide_ids]" value="">
                        @endif

                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <flux:heading size="sm">{{ $row['music_title'] }}</flux:heading>
                                @if($row['selected_song_id'])
                                    @php($selectedCandidate = collect($row['candidates'])->firstWhere('song_id', $row['selected_song_id']))
                                    <flux:text class="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                        {{ $selectedCandidate['label'] ?? '' }}
                                    </flux:text>
                                @elseif($row['status'] === 'ambiguous')
                                    <flux:badge color="amber">{{ __('Ambiguous match') }}</flux:badge>
                                @else
                                    <flux:badge color="red">{{ __('No safe match') }}</flux:badge>
                                @endif
                            </div>

                            @if(!$row['selected_song_id'])
                                <flux:checkbox wire:model.live="rows.{{ $rowIndex }}.omitted" :label="__('Deliberately omit this music')" />
                            @endif
                        </div>

                        @if(count($row['candidates']) > 1)
                            <div class="flex flex-wrap gap-2">
                                @foreach($row['candidates'] as $candidate)
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="{{ $row['selected_song_id'] === $candidate['song_id'] ? 'primary' : 'ghost' }}"
                                        wire:click="selectCandidate({{ $rowIndex }}, {{ $candidate['song_id'] }})">
                                        {{ $candidate['label'] }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endif

                        @if($row['selected_song_id'])
                            <div class="space-y-2">
                                @foreach($row['selected_slide_ids'] as $slideIndex => $slideId)
                                    @php($slide = $this->selectedSlide($rowIndex, $slideId))
                                    @if($slide)
                                        <div wire:key="diatar-selected-{{ $row['assignment_id'] }}-{{ $slideIndex }}" class="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-800">
                                            <span class="min-w-0 flex-1 text-sm">
                                                {{ $slide['verse_name'] ?: __('Unnamed verse') }}
                                                <span class="font-mono text-xs text-gray-500">{{ $slide['external_id'] }}</span>
                                            </span>
                                            <flux:button type="button" size="xs" variant="ghost" wire:click="moveSlide({{ $rowIndex }}, {{ $slideIndex }}, -1)" :disabled="$slideIndex === 0">↑</flux:button>
                                            <flux:button type="button" size="xs" variant="ghost" wire:click="moveSlide({{ $rowIndex }}, {{ $slideIndex }}, 1)" :disabled="$slideIndex === count($row['selected_slide_ids']) - 1">↓</flux:button>
                                            <flux:button type="button" size="xs" variant="ghost" wire:click="repeatSlide({{ $rowIndex }}, {{ $slideIndex }})">{{ __('Repeat') }}</flux:button>
                                            <flux:button type="button" size="xs" variant="ghost" wire:click="removeSlide({{ $rowIndex }}, {{ $slideIndex }})">{{ __('Remove') }}</flux:button>
                                        </div>
                                    @endif
                                @endforeach

                                @php($candidate = collect($row['candidates'])->firstWhere('song_id', $row['selected_song_id']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($candidate['slides'] ?? [] as $slide)
                                        <flux:button type="button" size="xs" variant="ghost" wire:click="addSlide({{ $rowIndex }}, {{ $slide['id'] }})">
                                            + {{ $slide['verse_name'] ?: __('Unnamed verse') }}
                                        </flux:button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <flux:error name="rows.{{ $rowIndex }}.song_id" />
                        <flux:error name="rows.{{ $rowIndex }}.slide_ids" />
                    </flux:card>
                @empty
                    <flux:callout icon="information-circle">{{ __('This music plan has no assigned music to export.') }}</flux:callout>
                @endforelse

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="$set('show', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary" :disabled="! $this->canConfirm">
                        {{ __('Download .dia') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
