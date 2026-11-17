<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <flux:card class="space-y-4 p-4 lg:p-6">
            <div class="flex items-center justify-between gap-2">
                <flux:button variant="ghost" size="sm" icon="arrow-left" :href="route('seasons')" wire:navigate>
                    {{ __('Season planner') }}
                </flux:button>

                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('More')" />
                    <flux:menu>
                        <flux:menu.item
                            icon="trash"
                            variant="danger"
                            wire:click="delete"
                            wire:confirm="{{ __('Delete this season with all its dates and answers?') }}"
                        >
                            {{ __('Delete season') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>

            <form wire:submit="saveDetails" class="space-y-3">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" :placeholder="__('Anything the choir should know about the season.')" />
                <div class="flex justify-end">
                    <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>

            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Share with the choir') }}</flux:heading>
                <flux:subheading class="mb-3">
                    {{ __('Whoever has the link sees the dates and what is attached to them, and can say under their own name which dates they are coming to. Booklets and plans attached to a date open from the season page while the season has a live link.') }}
                </flux:subheading>
                <livewire:loan-links :lendable="$season" :key="'season-loans-'.$season->id" />
            </div>
        </flux:card>

        <flux:card class="p-4 lg:p-6">
            <x-mary-tabs wire:model="tab">
                <x-mary-tab name="events" :label="__('Dates')" icon="o-calendar-days">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <flux:button variant="primary" icon="plus" wire:click="newEvent">{{ __('New date') }}</flux:button>

                        @if($this->pastCount > 0)
                            <flux:switch wire:model.live="showPast" :label="__('Show past dates (:count)', ['count' => $this->pastCount])" align="left" />
                        @endif
                    </div>

                    @if($this->events->isEmpty())
                        <flux:callout variant="secondary" icon="calendar-days" class="border-dashed">
                            <flux:callout.heading>{{ __('No dates yet') }}</flux:callout.heading>
                            <flux:callout.text>{{ __('Add the Masses, concerts and rehearsals of the season.') }}</flux:callout.text>
                        </flux:callout>
                    @else
                        <div class="space-y-2">
                            @foreach($this->events as $event)
                                @php($counts = $event->attendanceCounts())
                                <div
                                    wire:key="season-event-{{ $event->id }}"
                                    @class([
                                        'rounded-lg border border-l-4 border-zinc-200 px-4 py-3 dark:border-zinc-700',
                                        $event->celebration?->liturgicalBorderColorClass() ?? 'border-l-zinc-300! dark:border-l-zinc-600!',
                                        'opacity-60' => $event->isPast(),
                                    ])
                                >
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0 space-y-1">
                                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                                                {{ $event->date->translatedFormat('Y. F j., l') }}
                                                @if($event->timeLabel())
                                                    · {{ $event->timeLabel() }}
                                                @endif
                                            </div>
                                            <div class="font-medium">{{ $event->name }}</div>

                                            <div class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                                                @if($event->celebration && $event->celebration->name !== $event->name)
                                                    <span>{{ $event->celebration->name }}</span>
                                                @endif
                                                @if($event->musicPlan)
                                                    <a href="{{ route('music-plan-editor', ['musicPlan' => $event->musicPlan->id]) }}" wire:navigate class="inline-flex items-center gap-1 hover:underline">
                                                        <flux:icon name="list-music" variant="micro" />
                                                        {{ $event->musicPlan->celebration_name ?? __('Music Plan') }}
                                                    </a>
                                                @endif
                                            </div>

                                            @if($event->note)
                                                <flux:text class="whitespace-pre-line text-sm">{{ $event->note }}</flux:text>
                                            @endif

                                            @if($event->attachments->isNotEmpty())
                                                <div class="flex flex-wrap gap-1.5 pt-1">
                                                    @foreach($event->attachments as $attachment)
                                                        <span wire:key="attachment-{{ $attachment->id }}" class="inline-flex items-center gap-1 rounded-md border border-zinc-200 px-2 py-0.5 text-xs dark:border-zinc-700">
                                                            <flux:icon :name="$attachment->icon()" variant="micro" class="text-zinc-400" />
                                                            @if($attachment->ownerUrl())
                                                                <a href="{{ $attachment->ownerUrl() }}" @if($attachment->isLink()) target="_blank" rel="noopener noreferrer" @else wire:navigate @endif class="max-w-48 truncate hover:underline">{{ $attachment->title() }}</a>
                                                            @else
                                                                <span class="max-w-48 truncate">{{ $attachment->title() }}</span>
                                                            @endif
                                                            <button type="button" wire:click="detach({{ $attachment->id }})" class="text-zinc-400 hover:text-red-500" aria-label="{{ __('Remove') }}">
                                                                <flux:icon name="x-mark" variant="micro" />
                                                            </button>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex shrink-0 flex-wrap items-center gap-1">
                                            <flux:badge size="sm" color="green" icon="check">{{ $counts['yes'] }}</flux:badge>
                                            <flux:badge size="sm" color="amber" icon="question-mark-circle">{{ $counts['maybe'] }}</flux:badge>
                                            <flux:badge size="sm" color="red" icon="x-mark">{{ $counts['no'] }}</flux:badge>

                                            <flux:button size="sm" variant="ghost" icon="paper-clip" wire:click="openAttach({{ $event->id }})" :aria-label="__('Attach')" />
                                            <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editEvent({{ $event->id }})" :aria-label="__('Edit')" />
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="deleteEvent({{ $event->id }})"
                                                wire:confirm="{{ __('Delete this date and the answers given for it?') }}"
                                                :aria-label="__('Delete')"
                                            />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-mary-tab>

                <x-mary-tab name="attendance" :label="__('Attendance')" icon="o-users">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <form x-data="{ name: '' }" x-on:submit.prevent="if (name.trim()) { $wire.addMember(name); name = '' }" class="flex gap-2">
                            <flux:input x-model="name" size="sm" :placeholder="__('Add a member by name')" />
                            <flux:button type="submit" size="sm" icon="plus">{{ __('Add') }}</flux:button>
                        </form>

                        @if($this->pastCount > 0)
                            <flux:switch wire:model.live="showPast" :label="__('Show past dates (:count)', ['count' => $this->pastCount])" align="left" />
                        @endif
                    </div>

                    @if($this->members->isEmpty() || $this->events->isEmpty())
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('Once the choir starts answering on the link, who is coming to which date shows up here. You can also record answers yourself by clicking a cell.') }}
                        </flux:text>
                    @else
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                        <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-medium dark:bg-zinc-800"></th>
                                        @foreach($this->events as $event)
                                            <th wire:key="head-{{ $event->id }}" class="min-w-20 px-2 py-2 text-center align-bottom text-xs font-normal">
                                                <div class="font-medium">{{ $event->date->translatedFormat('M j.') }}</div>
                                                <div class="mx-auto max-w-24 truncate text-zinc-500" title="{{ $event->name }}">{{ $event->name }}</div>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                    @foreach($this->members as $member)
                                        <tr wire:key="member-{{ $member->id }}">
                                            <td class="sticky left-0 z-10 bg-white px-2 py-1 dark:bg-zinc-800">
                                                <div class="flex items-center gap-1">
                                                    <input
                                                        type="text"
                                                        value="{{ $member->name }}"
                                                        maxlength="60"
                                                        x-on:change="$wire.renameMember({{ $member->id }}, $event.target.value)"
                                                        class="w-32 rounded border border-transparent bg-transparent px-1 py-0.5 hover:border-zinc-200 focus:border-zinc-400 focus:outline-none dark:hover:border-zinc-600"
                                                        aria-label="{{ __('Name') }}"
                                                    />
                                                    @if($member->user_id)
                                                        <flux:icon name="user-circle" variant="micro" class="text-zinc-400" />
                                                    @endif
                                                    <button
                                                        type="button"
                                                        wire:click="deleteMember({{ $member->id }})"
                                                        wire:confirm="{{ __('Remove :name and all their answers?', ['name' => $member->name]) }}"
                                                        class="text-zinc-300 hover:text-red-500"
                                                        aria-label="{{ __('Remove') }}"
                                                    >
                                                        <flux:icon name="x-mark" variant="micro" />
                                                    </button>
                                                </div>
                                            </td>
                                            @foreach($this->events as $event)
                                                @php($answer = $this->answers[$event->id.'-'.$member->id] ?? null)
                                                <td wire:key="cell-{{ $event->id }}-{{ $member->id }}" class="px-2 py-1 text-center">
                                                    <button
                                                        type="button"
                                                        wire:click="cycleAnswer({{ $event->id }}, {{ $member->id }})"
                                                        @class([
                                                            'inline-flex h-7 w-7 items-center justify-center rounded-md border',
                                                            'border-green-300 bg-green-100 text-green-700 dark:border-green-700 dark:bg-green-900/40 dark:text-green-300' => $answer === \App\Enums\AttendanceStatus::Yes,
                                                            'border-amber-300 bg-amber-100 text-amber-700 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $answer === \App\Enums\AttendanceStatus::Maybe,
                                                            'border-red-300 bg-red-100 text-red-700 dark:border-red-700 dark:bg-red-900/40 dark:text-red-300' => $answer === \App\Enums\AttendanceStatus::No,
                                                            'border-dashed border-zinc-200 text-zinc-300 dark:border-zinc-700' => $answer === null,
                                                        ])
                                                        title="{{ $answer?->label() ?? __('No answer') }}"
                                                    >
                                                        @if($answer)
                                                            <flux:icon :name="$answer->icon()" variant="micro" />
                                                        @endif
                                                    </button>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-zinc-200 text-xs dark:border-zinc-700">
                                        <td class="sticky left-0 z-10 bg-white px-3 py-2 font-medium dark:bg-zinc-800">{{ __('Coming') }}</td>
                                        @foreach($this->events as $event)
                                            @php($counts = $event->attendanceCounts())
                                            <td wire:key="foot-{{ $event->id }}" class="px-2 py-2 text-center">
                                                <span class="font-semibold text-green-700 dark:text-green-400">{{ $counts['yes'] }}</span>
                                                @if($counts['maybe'] > 0)
                                                    <span class="text-amber-600 dark:text-amber-400">+{{ $counts['maybe'] }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </x-mary-tab>
            </x-mary-tabs>
        </flux:card>
    </div>

    <flux:modal name="season-event" class="w-full max-w-lg">
        <form wire:submit="saveEvent" class="space-y-4">
            <flux:heading size="lg">{{ $editingEventId ? __('Edit date') : __('New date') }}</flux:heading>

            <div class="grid grid-cols-2 gap-3">
                <flux:input type="date" wire:model.live="eventDate" :label="__('Date')" required />
                <flux:input type="time" wire:model="eventTime" :label="__('Time')" />
            </div>

            @if(count($this->liturgicalOptions) > 0)
                <div class="space-y-1">
                    <flux:label>{{ __('Celebration of the day') }}</flux:label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($this->liturgicalOptions as $option)
                            <flux:button
                                size="xs"
                                wire:key="liturgical-{{ $eventDate }}-{{ $option['index'] }}"
                                wire:click="chooseCelebration({{ $option['index'] }})"
                                :variant="$this->selectedCelebration?->name === $option['name'] ? 'primary' : 'filled'"
                            >
                                {{ $option['name'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->selectedCelebration)
                <div class="flex items-center gap-2 text-sm">
                    <flux:badge size="sm" icon="sparkles">{{ $this->selectedCelebration->name }}</flux:badge>
                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="clearCelebration">{{ __('Unlink') }}</flux:button>
                </div>
            @endif

            <flux:input wire:model="eventName" :label="__('Name')" :placeholder="__('e.g. Midnight Mass, Rehearsal')" required />

            <flux:select wire:model="musicPlanId" :label="__('Music plan')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach($this->selectablePlans as $plan)
                    <flux:select.option :value="$plan->id" wire:key="plan-option-{{ $plan->id }}">
                        {{ $plan->celebration_name ?? __('Untitled plan') }}@if($plan->actual_date) – {{ $plan->actual_date->translatedFormat('Y. F j.') }}@endif
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="eventNote" :label="__('Note')" rows="2" :placeholder="__('Where to meet, what to bring…')" />

            @unless($editingEventId)
                <flux:input type="date" wire:model="repeatUntil" :label="__('Repeat weekly until')" :description="__('Optional, for a run of rehearsals.')" />
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="season-attach" class="w-full max-w-lg">
        @if($this->attachEvent)
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Attach to :name', ['name' => $this->attachEvent->name]) }}</flux:heading>
                    <flux:subheading>{{ $this->attachEvent->date->translatedFormat('Y. F j., l') }}</flux:subheading>
                </div>

                @php($attachedBooklets = $this->attachEvent->attachments->where('lendable_type', \App\Models\Booklet::class)->pluck('lendable_id')->all())

                @if($this->attachEvent->musicPlan)
                    @php($planAttached = $this->attachEvent->attachments->where('lendable_type', \App\Models\MusicPlan::class)->where('lendable_id', $this->attachEvent->music_plan_id)->isNotEmpty())
                    <div class="space-y-1">
                        <flux:label>{{ __('Music plan') }}</flux:label>
                        <button
                            type="button"
                            wire:click="attachPlan"
                            @disabled($planAttached)
                            class="flex w-full items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-2 text-left hover:border-blue-500 disabled:opacity-50 disabled:hover:border-zinc-200 dark:border-zinc-700"
                        >
                            <span class="flex items-center gap-2 text-sm">
                                <flux:icon name="list-music" variant="micro" class="text-zinc-400" />
                                {{ $this->attachEvent->musicPlan->celebration_name ?? __('Music Plan') }}
                            </span>
                            <flux:icon :name="$planAttached ? 'check' : 'plus'" variant="micro" class="text-zinc-400" />
                        </button>
                    </div>
                @endif

                <div class="space-y-2">
                    <flux:label>{{ __('Booklets') }}</flux:label>
                    <flux:input type="search" size="sm" wire:model.live.debounce.400ms="bookletSearch" icon="magnifying-glass" :placeholder="__('Search')" />
                    <div class="max-h-64 space-y-1 overflow-y-auto">
                        @forelse($this->attachableBooklets as $booklet)
                            @php($isAttached = in_array($booklet->id, $attachedBooklets, true))
                            <button
                                type="button"
                                wire:key="attach-booklet-{{ $booklet->id }}"
                                wire:click="attachBooklet({{ $booklet->id }})"
                                @disabled($isAttached)
                                class="flex w-full items-center justify-between gap-3 rounded-md border border-zinc-200 px-3 py-2 text-left hover:border-blue-500 disabled:opacity-50 disabled:hover:border-zinc-200 dark:border-zinc-700"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $booklet->title }}</span>
                                    @if($booklet->musicPlan)
                                        <span class="block truncate text-xs text-zinc-500">{{ $booklet->musicPlan->celebration_name }}</span>
                                    @endif
                                </span>
                                <flux:icon :name="$isAttached ? 'check' : 'plus'" variant="micro" class="shrink-0 text-zinc-400" />
                            </button>
                        @empty
                            <flux:text class="text-sm text-zinc-500">{{ __('No booklets found.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <form wire:submit="attachLink" class="space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:label>{{ __('Link') }}</flux:label>
                    <flux:text class="text-xs">{{ __('Scores or recordings kept elsewhere — Google Drive, OneDrive, YouTube.') }}</flux:text>
                    <flux:input type="url" wire:model="linkUrl" placeholder="https://" />
                    <flux:input wire:model="linkLabel" :placeholder="__('Label (optional)')" />
                    <div class="flex justify-end">
                        <flux:button type="submit" size="sm" icon="link">{{ __('Attach link') }}</flux:button>
                    </div>
                </form>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Done') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
