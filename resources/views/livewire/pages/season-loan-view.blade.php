<div class="py-6">
    <div class="mx-auto w-full max-w-3xl space-y-4 px-4 sm:px-6">
        <flux:card class="space-y-2 p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-2">
                <flux:icon name="calendar-days" class="text-zinc-400" />
                <flux:heading size="xl">{{ $this->season->name }}</flux:heading>
            </div>
            @if($ownerName)
                <flux:text class="text-sm">{{ __('Shared by :name', ['name' => $ownerName]) }}</flux:text>
            @endif
            @if($this->season->description)
                <flux:text class="whitespace-pre-line">{{ $this->season->description }}</flux:text>
            @endif
        </flux:card>

        {{-- Who is answering. Asked once per browser; the name is remembered. --}}
        <flux:card class="p-4 sm:p-5">
            @if($this->member)
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:text>
                        {!! __('You are answering as <strong>:name</strong>.', ['name' => e($this->member->name)]) !!}
                    </flux:text>
                    <flux:button size="sm" variant="ghost" wire:click="forget">{{ __('Not me') }}</flux:button>
                </div>
            @else
                <div class="space-y-3">
                    <flux:heading size="lg">{{ __('Who are you?') }}</flux:heading>
                    <flux:text class="text-sm">{{ __('Pick your name, or add it if it is not on the list yet. This browser will remember it.') }}</flux:text>

                    @if($this->members->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($this->members as $member)
                                <flux:button size="sm" wire:key="pick-{{ $member->id }}" wire:click="identify({{ $member->id }})">
                                    {{ $member->name }}
                                </flux:button>
                            @endforeach
                        </div>
                    @endif

                    <form wire:submit="join" class="flex gap-2">
                        <flux:input wire:model="newName" maxlength="60" :placeholder="__('Your name')" class="flex-1" />
                        <flux:button type="submit" variant="primary">{{ __('That is me') }}</flux:button>
                    </form>
                    <flux:error name="newName" />
                </div>
            @endif
        </flux:card>

        @if($this->pastCount > 0)
            <div class="flex justify-end">
                <flux:switch wire:model.live="showPast" :label="__('Show past dates (:count)', ['count' => $this->pastCount])" align="left" />
            </div>
        @endif

        @forelse($this->events as $event)
            @php
                $mine = $this->myAnswers[$event->id] ?? null;
                $byStatus = $event->attendances->groupBy(fn ($attendance) => $attendance->status->value);
            @endphp
            <flux:card
                wire:key="event-{{ $event->id }}"
                @class([
                    'space-y-3 border-l-4 p-4 sm:p-5',
                    $event->celebration?->liturgicalBorderColorClass() ?? 'border-l-zinc-300! dark:border-l-zinc-600!',
                    'opacity-60' => $event->isPast(),
                ])
            >
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        {{ $event->date->translatedFormat('Y. F j., l') }}
                        @if($event->timeLabel())
                            · {{ $event->timeLabel() }}
                        @endif
                    </div>
                    <flux:heading size="lg">{{ $event->name }}</flux:heading>
                    @if($event->celebration && $event->celebration->name !== $event->name)
                        <flux:text class="text-sm">{{ $event->celebration->name }}</flux:text>
                    @endif
                    @if($event->note)
                        <flux:text class="mt-1 whitespace-pre-line text-sm">{{ $event->note }}</flux:text>
                    @endif
                </div>

                @php($links = $event->attachments->filter(fn ($attachment) => $attachment->memberUrl() !== null))
                @if($links->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($links as $attachment)
                            <flux:button
                                size="sm"
                                wire:key="link-{{ $attachment->id }}"
                                :icon="$attachment->icon()"
                                :href="$attachment->memberUrl()"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ $attachment->title() }}
                            </flux:button>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700/50">
                    @if($this->member)
                        <div class="flex gap-1" role="group" aria-label="{{ __('Your answer') }}">
                            @foreach(\App\Enums\AttendanceStatus::cases() as $status)
                                <button
                                    type="button"
                                    wire:key="answer-{{ $event->id }}-{{ $status->value }}"
                                    wire:click="respond({{ $event->id }}, '{{ $status->value }}')"
                                    aria-pressed="{{ $mine === $status ? 'true' : 'false' }}"
                                    @class([
                                        'inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-sm transition',
                                        'border-green-500 bg-green-600 text-white' => $mine === $status && $status === \App\Enums\AttendanceStatus::Yes,
                                        'border-amber-500 bg-amber-500 text-white' => $mine === $status && $status === \App\Enums\AttendanceStatus::Maybe,
                                        'border-red-500 bg-red-600 text-white' => $mine === $status && $status === \App\Enums\AttendanceStatus::No,
                                        'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700/50' => $mine !== $status,
                                    ])
                                >
                                    <flux:icon :name="$status->icon()" variant="micro" />
                                    {{ $status->label() }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div x-data="{ open: false }" class="text-sm">
                        <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-2 text-zinc-600 hover:underline dark:text-zinc-300">
                            <span class="text-green-700 dark:text-green-400">{{ __(':count coming', ['count' => $byStatus->get('yes')?->count() ?? 0]) }}</span>
                            @if($byStatus->has('maybe'))
                                <span class="text-amber-600 dark:text-amber-400">{{ __(':count maybe', ['count' => $byStatus->get('maybe')->count()]) }}</span>
                            @endif
                            <flux:icon name="chevron-down" variant="micro" x-bind:class="open && 'rotate-180'" />
                        </button>
                        <div x-show="open" x-cloak class="mt-2 space-y-1 text-xs">
                            @foreach(\App\Enums\AttendanceStatus::cases() as $status)
                                @if($byStatus->has($status->value))
                                    <div>
                                        <span class="font-medium">{{ $status->label() }}:</span>
                                        {{ $byStatus->get($status->value)->map(fn ($attendance) => $attendance->member->name)->sort()->join(', ') }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </flux:card>
        @empty
            <flux:callout variant="secondary" icon="calendar-days" class="border-dashed">
                <flux:callout.text>{{ __('There are no upcoming dates in this season yet.') }}</flux:callout.text>
            </flux:callout>
        @endforelse
    </div>
</div>
