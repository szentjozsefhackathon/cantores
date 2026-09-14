@push('page-bundles')
resources/js/projection-remote.js
@endpush

{{-- The deck in the cantor's hand.

     Built for one thumb on an organ bench, and laid out in three bands that
     never move: the slide the room is reading across the top half, what is
     coming next under it, and the two controls that are pressed a hundred times
     a service across the bottom quarter. Nothing scrolls the page, because a
     page that scrolls is a page whose Next button is somewhere else by the time
     the verse ends.

     It engraves the same payload the wall does through the same renderer, so
     what the phone shows is what the room is looking at rather than a
     description of it — and behind a swipe it keeps the plan, which is the part
     no display program's remote can show.

     Nothing here writes through Livewire. The component draws the deck at mount
     and then Alpine talks JSON to the two endpoints, the same pair the wall
     uses. --}}
<div
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
    x-ref="stage"
    x-on:fullscreenchange.window="syncFullscreen()"
    x-on:touchstart.passive="onTouchStart($event)"
    x-on:touchend.passive="onTouchEnd($event)"
    class="fixed inset-0 z-50 flex flex-col overscroll-none bg-zinc-200 select-none dark:bg-black"
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

    {{-- ---------------------------------------------------------------
         The upper half: what the room is reading, as wide as the phone.
         --------------------------------------------------------------- --}}
    <div class="relative flex h-1/2 shrink-0 items-center justify-center overflow-hidden bg-zinc-300 dark:bg-zinc-900">
        <div
            x-ref="currentBox"
            class="w-full max-h-full overflow-hidden bg-white"
            x-bind:style="`aspect-ratio: ${aspectRatio};`"
            x-bind:class="blanked ? 'opacity-25' : ''"
            x-show="!waiting"
            wire:ignore
        ></div>

        {{-- The thin bar over the letterbox. Everything that is not driving the
             service lives here, small and out of the thumb's way. --}}
        <div class="absolute inset-x-0 top-0 flex items-center gap-1 bg-gradient-to-b from-black/50 to-transparent px-2 py-1.5 text-white">
            <a href="{{ route('projection-remote.decks', ['screen' => $screen->id]) }}" wire:navigate class="shrink-0 rounded p-1" aria-label="{{ __('Decks') }}">
                <flux:icon.squares-2x2 class="size-5" />
            </a>

            <span class="min-w-0 flex-1 truncate text-xs text-white/80" x-text="title || @js(__('Remote'))"></span>

            <span class="shrink-0 text-xs tabular-nums text-white/80" x-show="total > 0" x-cloak>
                <span x-text="index + 1"></span>/<span x-text="total"></span>
            </span>

            <button type="button" class="shrink-0 rounded p-1" x-on:click="toggleFullscreen()" x-show="fullscreenAvailable" aria-label="{{ __('Full screen') }}">
                <flux:icon.arrows-pointing-out class="size-5" x-show="!fullscreen" />
                <flux:icon.arrows-pointing-in class="size-5" x-show="fullscreen" x-cloak />
            </button>

            {{-- The plan is a swipe away, and a swipe is not discoverable. --}}
            <button type="button" class="shrink-0 rounded p-1" x-on:click="openList()" aria-label="{{ __('The plan') }}">
                <flux:icon.list-bullet class="size-5" />
            </button>
        </div>

        {{-- What the phone knows and the wall cannot say for itself. One line at
             the foot of the picture rather than a stack of callouts, because
             every pixel here was taken from the slide. --}}
        <div class="absolute inset-x-0 bottom-0 space-y-px px-2 pb-1 text-center text-xs" x-cloak>
            <div class="rounded bg-amber-500/90 px-2 py-0.5 text-amber-950" x-show="wallBehind">
                {{ __('The screen is still catching up with the last change.') }}
            </div>
            <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="preparing && !waiting">
                {{ __('The screen is preparing the deck…') }}
            </div>
            <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="ended && !waiting">
                {{ __('This projection has been closed on the screen.') }}
            </div>
            <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="busy">
                {{ __('Drawing the deck…') }}
            </div>
            <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="!busy && !waiting && total === 0">
                {{ __('This projection has no slides yet.') }}
            </div>
        </div>

        {{-- A screen with nothing on it. The page the phone could not show
             before, because it had no way to address a screen that was not
             already showing something. --}}
        <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-6 text-center" x-show="waiting" x-cloak>
            <flux:icon.tv class="size-8 text-zinc-500" />
            <div class="text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('This screen is showing nothing') }}
            </div>
            <flux:button variant="primary" size="sm" icon="play" href="{{ route('projection-remote.decks', ['screen' => $screen->id]) }}" wire:navigate>
                {{ __('Choose a deck') }}
            </flux:button>
        </div>
    </div>

    {{-- ---------------------------------------------------------------
         The quarter under it: what is coming, in the order it is coming.
         --------------------------------------------------------------- --}}
    <div
        x-ref="strip"
        data-scrolls
        class="flex h-1/4 shrink-0 touch-pan-x items-center gap-2 overflow-x-auto overflow-y-hidden px-2 py-2"
        x-bind:style="`--slide-ratio: ${aspectRatio};`"
        wire:ignore
    ></div>

    {{-- ---------------------------------------------------------------
         The bottom quarter: the thumb. 1 : 1 : 2, and nothing else.
         --------------------------------------------------------------- --}}
    <div class="flex h-1/4 shrink-0 items-stretch gap-2 px-2 pb-2">
        <button
            type="button"
            class="flex flex-1 items-center justify-center rounded-xl border transition-colors disabled:opacity-40"
            x-on:click="previous()"
            x-bind:class="pressed === 'previous'
                ? 'border-blue-500 bg-blue-600 text-white ring-4 ring-blue-400/50'
                : 'border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:active:bg-zinc-800'"
            x-bind:disabled="index === 0"
            aria-label="{{ __('Previous slide') }}"
        >
            <flux:icon.chevron-left class="size-8" />
        </button>

        <button
            type="button"
            class="flex flex-1 items-center justify-center rounded-xl border transition-colors"
            x-on:click="toggleBlank()"
            x-bind:class="pressed === 'blank'
                ? 'border-blue-500 bg-blue-600 text-white ring-4 ring-blue-400/50'
                : blanked
                    ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                    : 'border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:active:bg-zinc-800'"
            aria-label="{{ __('Blank the screen') }}"
        >
            <flux:icon.eye-slash class="size-7" />
        </button>

        <button
            type="button"
            class="flex flex-[2] items-center justify-center rounded-xl border transition-colors disabled:opacity-40"
            x-on:click="next()"
            x-bind:class="pressed === 'next'
                ? 'border-blue-500 bg-blue-600 text-white ring-4 ring-blue-400/50'
                : 'border-zinc-900 bg-zinc-900 text-white active:bg-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 dark:active:bg-zinc-200'"
            x-bind:disabled="index >= total - 1"
            aria-label="{{ __('Next slide') }}"
        >
            <flux:icon.chevron-right class="size-10" />
        </button>
    </div>

    {{-- ---------------------------------------------------------------
         Behind a swipe: the deck as the plan it came from.
         ---------------------------------------------------------------

         Off the main three bands entirely, because it is read once or twice a
         service — to find the Communion hymn while the Offertory is still being
         played — and the three bands are read all the way through it. --}}
    <div class="fixed inset-0 z-10" x-show="listOpen" x-cloak>
        <div class="absolute inset-0 bg-black/50" x-on:click="closeList()"></div>

        <div
            class="absolute inset-y-0 right-0 flex w-11/12 max-w-md flex-col bg-white shadow-xl dark:bg-zinc-900"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <div class="flex shrink-0 items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <span class="flex-1 text-sm font-medium">{{ __('The plan') }}</span>
                <button type="button" class="rounded p-1" x-on:click="closeList()" aria-label="{{ __('Close') }}">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 touch-pan-y space-y-1.5 overflow-y-auto px-3 py-3">
                {{-- Each row named the way the cantor asked for it: the music,
                     the score, the slot it stands in — never "slide 7", which
                     is the one thing about a row that nobody is looking for. --}}
                <template x-for="row in rows" x-bind:key="row.id">
                    <div
                        class="rounded-lg border px-3 py-2"
                        x-bind:class="row.current
                            ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800'
                            : 'border-zinc-200 dark:border-zinc-700'"
                    >
                        <button type="button" class="block w-full text-start" x-on:click="goToEntry(row.id); closeList()">
                            <div class="truncate text-xs uppercase tracking-wide text-zinc-400" x-show="row.slot" x-text="row.slot"></div>
                            <div class="truncate text-sm font-medium" x-text="row.heading"></div>
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
                                        x-on:click="goToSlide(row.id, slide.index); closeList()"
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

            {{-- The end of the service, said deliberately.
                 Not the back arrow: leaving the remote means this phone is done
                 driving and the wall keeps what it has, because stopping a
                 projection by accident mid-Mass is a far worse failure than a
                 slide left up after everyone has gone home. And down here,
                 behind a swipe, where a thumb reaching for Next cannot find
                 it. --}}
            <div class="shrink-0 border-t border-zinc-200 px-3 py-3 dark:border-zinc-700">
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
    </div>
</div>
