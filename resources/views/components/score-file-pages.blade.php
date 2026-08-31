@props([
    'pages' => [],
    'name' => 'score-file-pages',
    'heading' => null,
    'label' => null,
    'iconOnly' => false,
])

@php
$pageUrls = array_values($pages);
@endphp

@if($pageUrls !== [])
<div
    {{ $attributes }}
    x-data="{
        modal: @js($name),
        pages: @js($pageUrls),
        pageLabel: @js(__('Page :page of :total')),
        page: 1,
        opened: false,
        get caption() { return this.pageLabel.replace(':page', this.page).replace(':total', this.pages.length) },
        next() { this.page = Math.min(this.pages.length, this.page + 1) },
        previous() { this.page = Math.max(1, this.page - 1) },
    }"
    x-on:modal-show.document="if ($event.detail?.name === modal) { page = 1; opened = true }"
>
    <flux:modal.trigger :name="$name">
        @if($iconOnly)
        <flux:button icon="eye" variant="ghost" size="sm" :aria-label="$label ?? __('View sheet music')" />
        @else
        <flux:button icon="eye" variant="outline" size="sm">
            {{ $label ?? __('View sheet music') }}
        </flux:button>
        @endif
    </flux:modal.trigger>

    {{-- Arrow keys reach this element by bubbling out of the open dialog. --}}
    <flux:modal
        :name="$name"
        class="w-full max-w-4xl"
        x-on:keydown.arrow-left="previous()"
        x-on:keydown.arrow-right="next()">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $heading ?? __('Sheet music') }}</flux:heading>

            {{-- Pages are fetched only once the modal has been opened, and only the one on screen. --}}
            <div class="max-h-[70vh] overflow-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700">
                <template x-if="opened">
                    <img x-bind:src="pages[page - 1]" x-bind:alt="caption" class="w-full" />
                </template>
            </div>

            <template x-if="pages.length > 1">
                <div class="flex items-center justify-center gap-3">
                    <flux:button
                        icon="chevron-left"
                        variant="ghost"
                        size="sm"
                        :aria-label="__('Previous page')"
                        x-bind:disabled="page === 1"
                        x-on:click="previous()" />

                    <flux:text class="text-sm tabular-nums" x-text="caption" />

                    <flux:button
                        icon="chevron-right"
                        variant="ghost"
                        size="sm"
                        :aria-label="__('Next page')"
                        x-bind:disabled="page === pages.length"
                        x-on:click="next()" />
                </div>
            </template>
        </div>
    </flux:modal>
</div>
@endif
