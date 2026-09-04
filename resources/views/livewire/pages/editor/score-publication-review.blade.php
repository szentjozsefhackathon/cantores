<div class="py-6">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <flux:heading size="2xl">{{ __('Score publication review') }}</flux:heading>
            <flux:subheading>
                {{ __('Nothing reaches the public library until it is approved here. Check the licence, the provenance and every file before approving.') }}
            </flux:subheading>
        </div>

        <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
            <div class="space-y-3">
                <flux:field>
                    <flux:select wire:model.live="status" size="sm">
                        <flux:select.option value="submitted">{{ __('Awaiting review') }}</flux:select.option>
                        @foreach($statuses as $statusOption)
                        <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                        @endforeach
                        <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                @forelse($this->queue as $item)
                <flux:card
                    wire:key="publication-{{ $item->id }}"
                    class="cursor-pointer p-3 {{ $publicationId === $item->id ? 'ring-2 ring-accent' : '' }}"
                    wire:click="select({{ $item->id }})">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <flux:heading size="sm" class="truncate">{{ $item->score->title }}</flux:heading>
                            <flux:text class="truncate text-xs text-zinc-500">
                                {{ $item->submitter?->display_name ?? __('Unknown') }}
                                @if($item->submitted_at) · {{ $item->submitted_at->diffForHumans() }} @endif
                            </flux:text>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if($this->reportCounts[$item->id] ?? false)
                            <flux:badge size="sm" color="red" icon="flag">{{ $this->reportCounts[$item->id] }}</flux:badge>
                            @endif
                            <flux:badge size="sm" color="zinc">{{ $item->license->shortCode() }}</flux:badge>
                        </div>
                    </div>
                </flux:card>
                @empty
                <flux:card class="p-6 text-center">
                    <flux:text class="text-sm text-zinc-500">{{ __('Nothing here.') }}</flux:text>
                </flux:card>
                @endforelse
            </div>

            <div>
                @if($this->selected)
                @php($publication = $this->selected)
                <flux:card class="space-y-6 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="xl">{{ $publication->score->title }}</flux:heading>
                            @if($publication->score->music)
                            <flux:subheading>
                                <a href="{{ route('music-view', $publication->score->music) }}" target="_blank" class="hover:underline">
                                    {{ $publication->score->music->title }}
                                </a>
                            </flux:subheading>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm">{{ $publication->status->label() }}</flux:badge>
                            @if($publication->hasUnpublishedChanges())
                            <flux:badge size="sm" color="amber">{{ __('Newer version awaiting review') }}</flux:badge>
                            @endif
                            <flux:button
                                size="sm"
                                variant="outline"
                                icon="arrow-top-right-on-square"
                                target="_blank"
                                :href="$publication->score->publicUrl()">
                                {{ __('Open the score') }}
                            </flux:button>
                        </div>
                    </div>

                    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Claimed basis') }}</dt>
                            <dd>{{ $publication->license->label() }}</dd>
                        </div>
                        @if($publication->outbound_license)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Offered to the public as') }}</dt>
                            <dd>{{ $publication->outbound_license->label() }}</dd>
                        </div>
                        @endif
                        @if($publication->source_url)
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Source') }}</dt>
                            <dd class="break-all">
                                <a href="{{ $publication->source_url }}" target="_blank" rel="noopener nofollow" class="underline">
                                    {{ $publication->source_title ?: $publication->source_url }}
                                </a>
                            </dd>
                        </div>
                        @endif
                        @if($publication->license->requiresEditionAffirmation())
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('The engraving itself') }}</dt>
                            <dd>
                                @if($publication->edition_is_free)
                                {{ __('Nominator affirms it is free: their own typesetting, or an edition published before :year.', ['year' => \App\Support\ScorePublicationRules::editionFreeBefore()]) }}
                                @else
                                {{ __('Not affirmed.') }}
                                @endif
                            </dd>
                        </div>
                        @endif
                        @if($publication->composer_death_year)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Composer died') }}</dt>
                            <dd>{{ $publication->composer_death_year }}</dd>
                        </div>
                        @endif
                    </dl>

                    @if($publication->rights_note)
                    <div>
                        <flux:heading size="sm">{{ __('The nominator says') }}</flux:heading>
                        <flux:text class="mt-1 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-400">{{ $publication->rights_note }}</flux:text>
                    </div>
                    @endif

                    @if($publication->permission_evidence)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/40">
                        <flux:heading size="sm">{{ __('Permission on record') }}</flux:heading>
                        <flux:text class="mt-1 whitespace-pre-line text-sm">{{ $publication->permission_evidence }}</flux:text>
                    </div>
                    @endif

                    <div>
                        <flux:heading size="sm">{{ __('Files this would publish') }}</flux:heading>
                        <div class="mt-2 space-y-2">
                            @foreach($this->reviewableFiles as $row)
                            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                                <flux:text class="flex-1 text-sm">{{ $row['file']->displayName() }}</flux:text>
                                <flux:badge size="sm" color="zinc">{{ $row['file']->rights->label() }}</flux:badge>
                                @if($row['publishable'])
                                <flux:badge size="sm" color="green">{{ __('Will be published') }}</flux:badge>
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="arrow-down-tray"
                                    target="_blank"
                                    :href="route('public-scores.file.download', ['score' => $publication->score, 'scoreFile' => $row['file']])">
                                    {{ __('Download') }}
                                </flux:button>
                                @else
                                <flux:badge size="sm" color="red">{{ __('Stays private') }}</flux:badge>
                                @endif
                            </div>
                            @endforeach

                            @if($this->reviewableFiles === [])
                            <flux:text class="text-sm text-zinc-500">{{ __('No uploaded files — this is a typed score.') }}</flux:text>
                            @endif
                        </div>
                    </div>

                    @if($this->openReports->isNotEmpty())
                    <div class="space-y-3 rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                        <flux:heading size="sm">{{ __('Rights complaints awaiting a decision') }}</flux:heading>

                        @foreach($this->openReports as $report)
                        <div class="space-y-2 border-t border-red-200 pt-3 first:border-t-0 first:pt-0 dark:border-red-900" wire:key="report-{{ $report->id }}">
                            <flux:text class="text-xs text-zinc-600 dark:text-zinc-400">
                                {{ __('Report :reference', ['reference' => '#'.$report->id]) }}
                                · {{ $report->capacity->label() }}
                                · {{ $report->created_at->diffForHumans() }}
                            </flux:text>
                            <flux:text class="text-sm whitespace-pre-line">{{ $report->claim }}</flux:text>
                            <flux:text class="text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $report->reporter_name }} · <a href="mailto:{{ $report->reporter_email }}" class="underline">{{ $report->reporter_email }}</a>
                            </flux:text>

                            <flux:field>
                                <flux:label>{{ __('Why the complaint does not stand') }}</flux:label>
                                <flux:textarea wire:model="reportNotes.{{ $report->id }}" rows="2" />
                                <flux:error name="reportNotes.{{ $report->id }}" />
                            </flux:field>
                            <flux:button size="sm" variant="outline" wire:click="dismissReport({{ $report->id }})">
                                {{ __('Dismiss the complaint') }}
                            </flux:button>
                        </div>
                        @endforeach

                        <flux:text class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ __('Taking the score down below answers every complaint listed here.') }}
                        </flux:text>
                    </div>
                    @endif

                    <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        @if($publication->status === \App\Enums\ScorePublicationStatus::TakenDown)
                        <div>
                            <flux:heading size="sm">{{ __('Why it was taken down') }}</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ $publication->takedown_reason }}</flux:text>
                        </div>
                        <flux:button variant="outline" wire:click="restore">{{ __('Return to the queue') }}</flux:button>
                        @elseif($publication->status === \App\Enums\ScorePublicationStatus::Approved)
                        <flux:field>
                            <flux:label>{{ __('Reason for removal') }}</flux:label>
                            <flux:textarea wire:model="takedownReason" rows="3" />
                            <flux:error name="takedownReason" />
                        </flux:field>
                        <flux:button variant="danger" wire:click="takeDown">{{ __('Take down') }}</flux:button>
                        @else
                        <flux:field>
                            <flux:label>{{ __('Notes for the nominator') }}</flux:label>
                            <flux:textarea wire:model="decisionNotes" rows="3" />
                            <flux:error name="decisionNotes" />
                        </flux:field>
                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="primary" icon="check" wire:click="approve">{{ __('Approve and publish') }}</flux:button>
                            <flux:button variant="danger" icon="x-mark" wire:click="reject">{{ __('Reject') }}</flux:button>
                        </div>
                        @endif
                    </div>
                </flux:card>
                @else
                <flux:card class="p-8 text-center">
                    <flux:text class="text-zinc-500">{{ __('Pick a nomination from the list.') }}</flux:text>
                </flux:card>
                @endif
            </div>
        </div>
    </div>
</div>
