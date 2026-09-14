@push('page-bundles')
resources/js/projection-remote.js
@endpush

{{-- The deck in the cantor's hand.

     Built for a thumb on an organ bench: the two controls that are pressed a
     hundred times a service are the two largest things on the page, and
     everything else is below the fold. It engraves the same payload the wall
     does through the same renderer, so what the phone shows is what the room is
     looking at rather than a description of it — and around that it shows the
     plan, which is the part no display program's remote can show.

     Nothing here writes through Livewire. The component draws the deck at mount
     and then Alpine talks JSON to the two endpoints, the same pair the wall
     uses. --}}
<div
    class="py-4"
    data-projection-config="{{ json_encode([
        'geometry' => $geometry,
        'entries' => $entries,
        'excluded' => $excluded,
        'revision' => $revision,
        'title' => $title,
        // The screen's URL is the constant; the presentation's two are not,
        // because the screen outlives the decks put on it. They are baked in
        // only for whatever was up at mount, and afterwards come from the
        // screen's own answer.
        'screenUrl' => route('screens.state', ['screen' => $screen->id]),
        'presentationId' => $presentation?->id,
        'stateUrl' => $presentation === null ? null : route('presentations.state', ['presentation' => $presentation->id]),
        'payloadUrl' => $presentation === null ? null : route('presentations.payload', ['presentation' => $presentation->id]),
        'csrfToken' => csrf_token(),
    ]) }}"
    x-data="projectionRemote(JSON.parse($el.dataset.projectionConfig))"
>
    {{-- The two engines that draw two of the four formats are globals rather
         than bundled modules, and must be loaded before anything asks for them. --}}
    <script src="https://cdn.jsdelivr.net/gh/bbloomf/exsurge@v1.22.1/dist/exsurge.min.js"></script>
    <script>
        window.abc2svg = window.abc2svg || {};
        (function() {
            var el = document.createElement('span');
            el.style.cssText = 'position:absolute;top:-9999px;left:-9999px;visibility:hidden;white-space:nowrap;';
            document.body.appendChild(el);
            window.abc2svg.el = el;
        })();
    </script>
    <script src="{{ \App\Support\VendorAsset::url('js/abc2svg-1.js') }}"></script>

    <div class="mx-auto max-w-2xl space-y-4 px-3">
        {{-- Out of here leads to the deck list, not back to a page that would
             redirect straight in again. Leaving the remote is this phone saying
             it is done driving; it does not touch what the room is looking at. --}}
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="squares-2x2" href="{{ route('projection-remote.decks', ['screen' => $screen->id]) }}" wire:navigate>
                {{ __('Decks') }}
            </flux:button>

            <span class="min-w-0 flex-1 truncate text-sm text-zinc-500" x-text="title || @js(__('Remote'))"></span>

            <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300" x-show="total > 0" x-cloak>
                <span x-text="index + 1"></span> / <span x-text="total"></span>
            </span>
        </div>

        {{-- A screen with nothing on it. The page the phone could not show
             before, because it had no way to address a screen that was not
             already showing something. --}}
        <div x-show="waiting" x-cloak>
            <flux:callout variant="secondary" icon="tv" class="border-dashed">
                <flux:callout.heading>{{ __('This screen is showing nothing') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Choose a deck and it will appear in the room.') }}
                </flux:callout.text>
            </flux:callout>

            <flux:button variant="primary" icon="play" class="mt-4 w-full" href="{{ route('projection-remote.decks', ['screen' => $screen->id]) }}" wire:navigate>
                {{ __('Choose a deck') }}
            </flux:button>
        </div>

        {{-- The one thing a remote can say that a display program's cannot:
             whether the room is already seeing the correction that was just
             saved, or is still engraving it. --}}
        <flux:callout variant="warning" icon="arrow-path" x-show="wallBehind" x-cloak class="py-2">
            <flux:callout.text>{{ __('The screen is still catching up with the last change.') }}</flux:callout.text>
        </flux:callout>

        {{-- Between two decks: the room is looking at black, and the cantor did
             not ask for black. Worth its own sentence for exactly that reason. --}}
        <flux:callout variant="secondary" icon="tv" x-show="preparing && !waiting" x-cloak class="py-2">
            <flux:callout.text>{{ __('The screen is preparing the deck…') }}</flux:callout.text>
        </flux:callout>

        <flux:callout variant="secondary" icon="tv" x-show="ended && !waiting" x-cloak class="py-2">
            <flux:callout.text>{{ __('This projection has been closed on the screen.') }}</flux:callout.text>
        </flux:callout>

        {{-- Everything below drives a deck, and there is nothing to drive on a
             screen that has none. --}}
        <div class="space-y-4" x-show="!waiting">

        {{-- What is on the wall, and what is about to be. The next slide is the
             reason this is a remote and not a clicker: the cantor can see what
             the room is about to read before the room reads it. --}}
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-400">{{ __('On the screen') }}</div>
                <div
                    x-ref="currentBox"
                    class="w-full overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700"
                    x-bind:style="`aspect-ratio: ${aspectRatio};`"
                    x-bind:class="blanked ? 'opacity-25' : ''"
                    wire:ignore
                ></div>
            </div>

            <div class="w-1/3 shrink-0">
                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-400">{{ __('Next') }}</div>
                <div
                    x-ref="nextBox"
                    class="w-full overflow-hidden rounded-lg border border-dashed border-zinc-200 bg-white dark:border-zinc-700"
                    x-bind:style="`aspect-ratio: ${aspectRatio};`"
                    wire:ignore
                ></div>
            </div>
        </div>

        {{-- Sized for a thumb on an organ bench during a hymn. --}}
        <div class="flex items-stretch gap-2">
            <button
                type="button"
                class="flex h-20 flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white active:bg-zinc-100 disabled:opacity-40 dark:border-zinc-700 dark:bg-zinc-900 dark:active:bg-zinc-800"
                x-on:click="previous()"
                x-bind:disabled="index === 0"
                aria-label="{{ __('Previous slide') }}"
            >
                <flux:icon.chevron-left class="size-8" />
            </button>

            <button
                type="button"
                class="flex h-20 w-24 shrink-0 items-center justify-center rounded-xl border transition-colors"
                x-on:click="toggleBlank()"
                x-bind:class="blanked
                    ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                    : 'border-zinc-200 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:active:bg-zinc-800'"
                aria-label="{{ __('Blank the screen') }}"
            >
                <flux:icon.eye-slash class="size-7" />
            </button>

            <button
                type="button"
                class="flex h-20 flex-[2] items-center justify-center rounded-xl bg-zinc-900 text-white active:bg-zinc-700 disabled:opacity-40 dark:bg-white dark:text-zinc-900 dark:active:bg-zinc-200"
                x-on:click="next()"
                x-bind:disabled="index >= total - 1"
                aria-label="{{ __('Next slide') }}"
            >
                <flux:icon.chevron-right class="size-10" />
            </button>
        </div>

        <div class="text-center text-xs text-zinc-400" x-show="busy" x-cloak>{{ __('Drawing the deck…') }}</div>

        {{-- The deck as the plan it came from: the slot, the music, the
             variation, and however many screens the row breaks into. A tap on a
             row jumps the room to it. --}}
        <div class="space-y-1.5">
            <template x-for="row in rows" x-bind:key="row.id">
                <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                    <button type="button" class="block w-full text-start" x-on:click="goToEntry(row.id)">
                        <div class="truncate text-sm font-medium" x-text="row.heading || '{{ __('Slide') }}'"></div>
                        <div class="truncate text-xs text-zinc-500" x-show="row.reference" x-text="row.reference"></div>
                    </button>

                    <div class="mt-1.5 flex flex-wrap gap-1">
                        <template x-for="slide in row.slides" x-bind:key="slide.index">
                            <div class="flex items-center">
                                {{-- A slide the deck shows: a number to jump to. --}}
                                <button
                                    type="button"
                                    class="min-w-8 rounded px-2 py-1 text-xs font-medium transition-colors"
                                    x-show="!slide.skipped || slide.revealed"
                                    x-on:click="goToSlide(row.id, slide.index)"
                                    x-bind:class="slide.current
                                        ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                        : (slide.revealed
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                                            : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300')"
                                    x-text="slide.index + 1"
                                ></button>

                                {{-- One the deck leaves out. Bringing it back is
                                     today's deviation and not an edit: the
                                     projection stays as its author arranged it,
                                     and the slide was engraved at load like
                                     every other, so this costs nothing. --}}
                                <button
                                    type="button"
                                    class="min-w-8 rounded border border-dashed border-zinc-300 px-2 py-1 text-xs text-zinc-400 dark:border-zinc-600"
                                    x-show="slide.skipped && !slide.revealed"
                                    x-on:click="toggleReveal(row.id, slide.index)"
                                    x-bind:aria-label="'{{ __('Bring this verse back for today') }}'"
                                    x-text="slide.index + 1"
                                ></button>

                                {{-- And putting it away again. --}}
                                <button
                                    type="button"
                                    class="px-1 text-xs text-amber-600 dark:text-amber-300"
                                    x-show="slide.skipped && slide.revealed"
                                    x-on:click="toggleReveal(row.id, slide.index)"
                                    x-bind:aria-label="'{{ __('Leave this verse out again') }}'"
                                >&times;</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <div class="text-center text-xs text-zinc-400" x-show="!busy && total === 0" x-cloak>
            {{ __('This projection has no slides yet.') }}
        </div>

        {{-- The end of the service, said deliberately.
             Not the back arrow: leaving the remote means this phone is done
             driving and the wall keeps what it has, because stopping a
             projection by accident mid-Mass is a far worse failure than a slide
             left up after everyone has gone home. --}}
        <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button
                variant="subtle"
                icon="power"
                class="w-full"
                x-on:click="confirm(@js(__('Take the deck off the screen and end this projection?'))) && clearScreen()"
            >
                {{ __('Clear the screen') }}
            </flux:button>
        </div>

        </div>

        <div class="pb-8"></div>
    </div>
</div>
