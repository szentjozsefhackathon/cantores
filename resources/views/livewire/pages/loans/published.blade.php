{{--
    Where every score I offered the public library stands.

    Status lives inside a single score's editor otherwise, so a rejected
    nomination is invisible until you happen to open that score.
--}}
@php($publications = $this->published)

@if($publications->isEmpty())
    <flux:callout variant="secondary" icon="globe-alt" class="border-dashed">
        <flux:callout.heading>{{ __('You have not offered any score to the public library') }}</flux:callout.heading>
        <flux:callout.text>{{ __('Publishing is separate from lending: it is open to strangers and reviewed first.') }}</flux:callout.text>
    </flux:callout>
@else
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Score') }}</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">{{ __('State') }}</flux:table.column>
            <flux:table.column class="hidden md:table-cell">{{ __('The public is reading') }}</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">{{ __("Reviewer's note") }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach($publications as $publication)
                <flux:table.row wire:key="publication-row-{{ $publication->id }}">
                    <flux:table.cell>
                        <div class="font-medium">
                            @if($publication->score)
                                <a href="{{ route('scores.edit', ['score' => $publication->score_id]) }}" wire:navigate class="hover:underline">
                                    {{ $publication->score->title }}
                                </a>
                            @else
                                {{ __('Deleted') }}
                            @endif
                        </div>
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $publication->submitted_at?->translatedFormat('Y-m-d') }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">
                        <flux:badge size="sm" :color="match($publication->status) {
                            \App\Enums\ScorePublicationStatus::Approved => 'green',
                            \App\Enums\ScorePublicationStatus::Submitted => 'amber',
                            \App\Enums\ScorePublicationStatus::Rejected, \App\Enums\ScorePublicationStatus::TakenDown => 'red',
                            default => 'zinc',
                        }">
                            {{ $publication->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">
                        @if($publication->approvedVersion)
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $publication->approvedVersion->created_at?->translatedFormat('Y-m-d') }}
                            </span>
                            @if($publication->hasUnpublishedChanges())
                                <div class="mt-0.5">
                                    <flux:badge size="sm" color="amber">{{ __('Newer version awaiting review') }}</flux:badge>
                                </div>
                            @endif
                        @else
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="hidden lg:table-cell">
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $publication->takedown_reason ?: ($publication->review_notes ?: '—') }}
                        </span>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @if($publications->hasPages())
        <div class="mt-4">{{ $publications->links() }}</div>
    @endif
@endif
