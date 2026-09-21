@push('page-bundles')
resources/js/projection-remote.js
@endpush

{{-- The deck in the cantor's hand, and the deck beside the projector.

     On a phone: one thumb on an organ bench, laid out in three bands that
     never move — the slide the room is reading across the top half, what is
     coming next under it, and the two controls that are pressed a hundred times
     a service across the bottom quarter. Nothing scrolls the page, because a
     page that scrolls is a page whose Next button is somewhere else by the time
     the verse ends.

     On a laptop: three columns, which is what a laptop has room for and a
     phone has not. Down the left the deck read as the service it was made from
     — slots, their music, what the deck took from each — with the way into the
     editor at the top of it, for the changes that are meant to last. In the
     middle the service itself: the slide the room is reading, the controls, and
     the slide it is about to be on beneath them, readable, because what the
     cantor needs to see is not a thumbnail of the next verse but the next
     verse. Down the right every slide in the deck, each with the editor's
     control for taking one out of today's service or putting one back.

     The strip of thumbnails belongs to the phone alone. Two scrollable sheets
     of the same slides down two sides is one too many, and what the strip was
     for — the jump three verses on, made without reading — the deck pane does
     better with the whole deck in it.

     The controls stay large, because the laptop is often driven from across
     the room with a mouse as well as from its keyboard — and each carries the
     key that does the same, so the keyboard is learnt by looking at them. The
     slide the room is reading takes two thirds of the column's height and the
     next one the rest.

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
        'outline' => $outline,
        'revision' => $revision,
        'title' => $title,
        // The show's URL is the constant; the presentation's two are not,
        // because the show outlives the decks put up in it. They are baked in
        // only for whatever was up at mount, and afterwards come from the
        // show's own answer.
        'showUrl' => route('show.state'),
        // Where to ask to be told when the show moves, instead of asking.
        'streamUrl' => route('show.stream'),
        // The walls that are on, each with where its picture lands, so the
        // preview is lined up the way the wall is before the first poll.
        'screens' => $screens,
        // Which browser this is, so that the laptop running the wall in one
        // window and this page in the other still knows its own screen. The
        // show's answer names no device; this is what it is read against.
        'deviceId' => \App\Support\DeviceId::current(),
        'presentationId' => $presentation?->id,
        // How far into its opening the service is, so that the preview under the
        // thumb is the same picture the room is looking at — which is the whole
        // point of the card, since the beamer is lined up against it by the
        // person holding this.
        'splash' => $presentation?->splash ?? \App\Models\Presentation::SPLASH_OFF,
        // And what the next press will do about it. With two states there was no
        // way to tell how a cantor was meant to get out of the card at all.
        'cardHint' => __('Title card on the screen. Next blacks it out.'),
        'darkHint' => __('The screen is black. Next starts the deck, still black — B shows it.'),
        'stateUrl' => $presentation === null ? null : route('presentations.state', ['presentation' => $presentation->id]),
        'resyncUrl' => $presentation === null ? null : route('presentations.resync', ['presentation' => $presentation->id]),
        'payloadUrl' => $presentation === null ? null : route('presentations.payload', ['presentation' => $presentation->id]),
        'editUrl' => $projection === null ? null : route('projections.edit', ['projection' => $projection->id]),
        // Add or remove one of a music's engravings from here, exactly as the
        // editor's plan pane does — the deck stays the owner's own, so this is
        // offered wherever the edit link already is.
        'scoreToggleUrl' => $projection === null ? null : route('projections.score-toggle', ['projection' => $projection->id]),
        // And the rest of what the phone may change for good — moving things
        // into place, and a music the plan does not have. Rebound from the
        // show's answer whenever another deck is put up.
        ...($projection === null ? [] : \App\Services\ShowState::deckUrls($projection->id)),
        'removeAddedMusicText' => __('Remove this music and the scores chosen from it?'),
        'skipText' => __('Leave this slide out of today\'s service'),
        'unskipText' => __('Show this slide in today\'s service'),
        'skippedText' => __('Left out'),
        'addScoreText' => __('Add this to today\'s deck'),
        'removeScoreText' => __('Take this out of today\'s deck'),
        // Asked before the screen is cleared. Here rather than on the button
        // itself, because Blade leaves a directive inside a component's
        // attribute uncompiled and Alpine is then handed an expression it
        // cannot parse.
        'clearText' => __('Take the deck off every screen and end this projection?'),
        'sendingText' => __('Sending'),
        'waitingText' => __('Waiting for screen'),
        'updatedText' => __('Screen updated'),
        'notRespondingText' => __('Screen not responding'),
        'csrfToken' => csrf_token(),
    ]) }}"
    x-data="projectionRemote(JSON.parse($el.dataset.projectionConfig))"
    x-ref="stage"
    x-on:fullscreenchange.window="syncFullscreen()"
    {{-- The laptop's end of the same remote: the keys every display program
         taught the person standing at it, driving the screen next to it. --}}
    x-on:keydown.window="onKey($event)"
    x-on:touchstart.passive="onTouchStart($event)"
    x-on:touchend.passive="onTouchEnd($event)"
    class="fixed inset-0 z-50 flex flex-col overscroll-none bg-zinc-200 select-none lg:flex-row dark:bg-black"
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
         The laptop's left column: the deck read as the service it came from.
         ---------------------------------------------------------------

         The editor's plan pane with everything that edits it taken away. What
         it answers during a service is not "what is on slide 41" but "is the
         Communion hymn in, and which verses of it" — so it is slots, the music
         under each, and what the deck took from that music, each row saying how
         much of itself the room is being shown. Clicking one goes there.

         Not on the phone: the plan is behind the swipe there, where it says the
         same in rows the width of the screen. --}}
    <div class="hidden lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:border-e lg:border-zinc-300 lg:bg-zinc-100 xl:w-72 dark:lg:border-zinc-800 dark:lg:bg-zinc-950">
        <div class="flex shrink-0 items-center gap-2 border-b border-zinc-300 px-3 py-2 dark:border-zinc-800">
            <span class="min-w-0 flex-1 truncate text-xs font-medium" x-text="title || @js(__('The plan'))"></span>

            {{-- The deck itself, changed for good rather than for today. A real
                 link and not a button pretending to be one — it is opened in a
                 tab of its own, because this window is driving a service and a
                 service is not something to navigate away from by accident —
                 and the address is rebound as decks are swapped under it. --}}
            <flux:button
                size="xs"
                variant="subtle"
                icon="pencil-square"
                :href="$projection === null ? '#' : route('projections.edit', ['projection' => $projection->id])"
                x-bind:href="editUrl"
                x-show="editUrl"
                x-cloak
                target="_blank"
            >
                {{ __('Edit') }}
            </flux:button>
        </div>

        {{-- Changes that stay with the deck: a music the plan does not have,
             added at the end, and moving things into place. Reorder puts arrows
             on every line and stops a tap from going anywhere — off, the column
             is only for finding the Communion hymn. --}}
        <div class="flex shrink-0 items-center gap-1 border-b border-zinc-300 px-2 py-1 dark:border-zinc-800" x-show="moveUrl" x-cloak>
            <button
                type="button"
                class="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px]"
                x-on:click="toggleReorder()"
                x-bind:class="reorder ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800'"
                x-bind:aria-pressed="reorder ? 'true' : 'false'"
            >
                <flux:icon.arrows-up-down class="size-3" />
                {{ __('Reorder') }}
            </button>
            <span class="flex-1"></span>
            <button type="button" class="flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-amber-700 hover:bg-amber-100 dark:text-amber-300 dark:hover:bg-amber-900/40" x-on:click="openAddMusic(null)">
                <flux:icon.plus class="size-3" />
                {{ __('Add music') }}
            </button>
        </div>

        <div data-scrolls class="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain px-2 py-2">
            <template x-for="band in outline" x-bind:key="band.key">
                <div class="space-y-1">
                    {{-- The slot's name across the width, as in the editor: what
                         belongs to which part of the service is then told apart
                         without reading. Shown even where the deck has taken
                         nothing from it yet — the plan gives every slot a place,
                         and this is where a slot still waiting to be filled says
                         so. --}}
                    <div class="flex items-center gap-0.5 rounded bg-zinc-200 px-2 py-0.5 dark:bg-zinc-800" x-show="band.name">
                        <span class="min-w-0 flex-1 truncate text-[11px] font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300" x-text="band.name"></span>
                        <template x-if="reorder && band.moves">
                            <span class="flex shrink-0">
                                <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!band.moves.up" x-on:click="move('slot', band.slotId, -1)" aria-label="{{ __('Move this slot up') }}"><flux:icon.chevron-up class="size-3.5" /></button>
                                <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!band.moves.down" x-on:click="move('slot', band.slotId, 1)" aria-label="{{ __('Move this slot down') }}"><flux:icon.chevron-down class="size-3.5" /></button>
                            </span>
                        </template>
                        <button type="button" class="shrink-0 rounded p-0.5 text-amber-600 dark:text-amber-300" x-show="addedMusicsUrl && band.slotId" x-on:click="openAddMusic(band.slotId)" aria-label="{{ __('Add music to this slot, only in this projection') }}" title="{{ __('Add music to this slot, only in this projection') }}">
                            <flux:icon.plus class="size-3.5" />
                        </button>
                    </div>

                    <template x-if="band.blocks.length === 0">
                        <div class="ms-1 text-[11px] italic text-zinc-400">{{ __('Nothing chosen yet') }}</div>
                    </template>

                    <template x-for="block in band.blocks" x-bind:key="block.key">
                        <div class="ms-1 space-y-0.5">
                            <template x-if="block.kind === 'music'">
                                <div class="space-y-0.5" x-bind:class="block.local ? 'border-s-2 border-dashed border-amber-500 ps-1' : ''">
                                    <div class="flex items-center gap-1 text-xs font-medium" x-show="block.name">
                                        <flux:icon name="music" variant="micro" class="shrink-0" x-bind:class="block.local ? 'text-amber-500' : 'text-indigo-400'" />
                                        <span class="min-w-0 flex-1 truncate" x-text="block.name"></span>
                                        <template x-if="reorder">
                                            <span class="flex shrink-0">
                                                <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!block.moves.up" x-on:click="move(block.moveKind, block.moveId, -1)" aria-label="{{ __('Move this music up') }}"><flux:icon.chevron-up class="size-3.5" /></button>
                                                <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!block.moves.down" x-on:click="move(block.moveKind, block.moveId, 1)" aria-label="{{ __('Move this music down') }}"><flux:icon.chevron-down class="size-3.5" /></button>
                                            </span>
                                        </template>
                                        <button type="button" class="shrink-0 rounded p-0.5 text-zinc-400 hover:text-red-500" x-show="block.local" x-on:click="removeAddedMusic(block.addedMusicId, block.rows.length)" aria-label="{{ __('Remove this music from the projection') }}" title="{{ __('Remove this music from the projection') }}">
                                            <flux:icon.trash class="size-3.5" />
                                        </button>
                                    </div>
                                    <div class="text-[10px] text-amber-700 dark:text-amber-300" x-show="block.local">{{ __('Only in this projection') }}</div>

                                    {{-- One engraving of that music, named as the
                                         editor names it — the score, the file
                                         chosen out of it, the variation, and the
                                         opening notes beneath — because a slot
                                         sung from three engravings of one music
                                         is three rows that share every word of
                                         their title. And how much of it today is
                                         being shown: 3/5 is the whole answer to
                                         "which verses", and it is the number that
                                         changes when a verse is taken out in the
                                         column opposite. --}}
                                    <template x-for="row in block.rows" x-bind:key="row.id">
                                        <div class="flex items-start gap-1">
                                            <button
                                                type="button"
                                                class="block min-w-0 flex-1 rounded px-2 py-1 text-start text-xs"
                                                x-on:click="reorder || goToEntry(row.id)"
                                                x-bind:class="row.current
                                                    ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                                    : (row.shownCount === 0
                                                        ? 'text-zinc-400 line-through dark:text-zinc-600'
                                                        : 'hover:bg-zinc-200 dark:hover:bg-zinc-800')"
                                            >
                                                <span class="flex items-center gap-1.5">
                                                    <span class="min-w-0 flex-1 truncate" x-bind:class="row.isText ? 'italic' : ''" x-text="rowName(row)"></span>
                                                    <span class="shrink-0 text-[10px] tabular-nums opacity-70" x-text="`${row.shownCount}/${row.slideCount}`"></span>
                                                </span>

                                                <span class="block truncate text-[10px] opacity-60" x-show="row.variationName" x-text="row.variationName"></span>

                                                {{-- The opening notes. Plain rather than the
                                                     editor's zoomable strip: this is read at a
                                                     glance during a service, and a dialog over
                                                     the remote is the last thing anybody wants
                                                     open while pressing Next. --}}
                                                <img
                                                    class="mt-0.5 max-h-12 max-w-full rounded bg-white object-contain"
                                                    x-show="row.incipit"
                                                    x-bind:src="row.incipit"
                                                    alt=""
                                                    loading="lazy"
                                                />
                                            </button>

                                            <template x-if="reorder">
                                                <span class="flex shrink-0 flex-col">
                                                    <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!row.moves.up" x-on:click="move('entry', row.id, -1)" aria-label="{{ __('Move up') }}"><flux:icon.chevron-up class="size-3.5" /></button>
                                                    <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!row.moves.down" x-on:click="move('entry', row.id, 1)" aria-label="{{ __('Move down') }}"><flux:icon.chevron-down class="size-3.5" /></button>
                                                </span>
                                            </template>

                                            {{-- Taking a whole engraving back out of today's
                                                 deck — a text row has no score behind it and
                                                 so nothing here to take out. --}}
                                            <button
                                                type="button"
                                                class="shrink-0 rounded p-1 text-zinc-400 hover:text-red-500"
                                                x-show="row.scoreId"
                                                x-on:click="toggleScore(row.scoreId, row.assignmentId, row.fileId, row.addedMusicId)"
                                                x-bind:aria-label="removeScoreText"
                                                :title="removeScoreText"
                                            >
                                                <flux:icon.minus-circle class="size-3.5" />
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="block.rows.length === 0">
                                        <div class="text-[10px] italic text-zinc-400">{{ __('Not sung from yet') }}</div>
                                    </template>

                                    {{-- What the music could still be sung from: every
                                         other engraving the plan offers, one click from
                                         standing beside the rows above it. --}}
                                    <template x-for="offer in block.offers" x-bind:key="`offer-${offer.scoreId}-${offer.fileId ?? 0}`">
                                        <button
                                            type="button"
                                            class="block w-full rounded border border-dashed border-zinc-300 px-2 py-1 text-start text-[11px] text-zinc-400 disabled:opacity-40 dark:border-zinc-600"
                                            x-on:click="toggleScore(offer.scoreId, block.assignmentId, offer.fileId, block.addedMusicId)"
                                            x-bind:disabled="offer.inBooklets === false"
                                            x-bind:aria-label="addScoreText"
                                            :title="addScoreText"
                                        >
                                            <span class="flex items-center gap-1.5">
                                                <flux:icon.plus class="size-3 shrink-0" />
                                                <span class="min-w-0 flex-1 truncate" x-text="offer.title"></span>
                                            </span>

                                            {{-- Greyed, same as the swipe list's: an offer is a
                                                 score not yet in today's deck, and the incipit
                                                 says so at a glance rather than only through the
                                                 dashed border around it. --}}
                                            <img
                                                class="mt-0.5 max-h-12 max-w-full rounded bg-white object-contain opacity-50"
                                                x-show="offer.incipitUrl"
                                                x-bind:src="offer.incipitUrl"
                                                alt=""
                                                loading="lazy"
                                            />
                                        </button>
                                    </template>
                                </div>
                            </template>

                            {{-- A row belonging to no music at all — a screen of
                                 words, or a score chosen outside the plan. --}}
                            <template x-if="block.kind === 'row'">
                                <div class="flex items-start gap-1">
                                    <button
                                        type="button"
                                        class="block min-w-0 flex-1 rounded px-2 py-1 text-start text-xs"
                                        x-on:click="reorder || goToEntry(block.row.id)"
                                        x-bind:class="block.row.current
                                            ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                            : (block.row.shownCount === 0
                                                ? 'text-zinc-400 line-through dark:text-zinc-600'
                                                : 'hover:bg-zinc-200 dark:hover:bg-zinc-800')"
                                    >
                                        <span class="flex items-center gap-1.5">
                                            <span class="min-w-0 flex-1 truncate" x-bind:class="block.row.isText ? 'italic' : ''" x-text="rowName(block.row)"></span>
                                            <span class="shrink-0 text-[10px] tabular-nums opacity-70" x-text="`${block.row.shownCount}/${block.row.slideCount}`"></span>
                                        </span>

                                        <span class="block truncate text-[10px] opacity-60" x-show="block.row.variationName" x-text="block.row.variationName"></span>

                                        <img
                                            class="mt-0.5 max-h-12 max-w-full rounded bg-white object-contain"
                                            x-show="block.row.incipit"
                                            x-bind:src="block.row.incipit"
                                            alt=""
                                            loading="lazy"
                                        />
                                    </button>

                                    <template x-if="reorder">
                                        <span class="flex shrink-0 flex-col">
                                            <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!block.row.moves.up" x-on:click="move('entry', block.row.id, -1)" aria-label="{{ __('Move up') }}"><flux:icon.chevron-up class="size-3.5" /></button>
                                            <button type="button" class="rounded p-0.5 text-zinc-500 disabled:opacity-30" x-bind:disabled="!block.row.moves.down" x-on:click="move('entry', block.row.id, 1)" aria-label="{{ __('Move down') }}"><flux:icon.chevron-down class="size-3.5" /></button>
                                        </span>
                                    </template>

                                    <button
                                        type="button"
                                        class="shrink-0 rounded p-1 text-zinc-400 hover:text-red-500"
                                        x-show="block.row.scoreId"
                                        x-on:click="toggleScore(block.row.scoreId, block.row.assignmentId, block.row.fileId)"
                                        x-bind:aria-label="removeScoreText"
                                        :title="removeScoreText"
                                    >
                                        <flux:icon.minus-circle class="size-3.5" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ---------------------------------------------------------------
         The service: three bands on a phone, the middle column on a laptop.
         --------------------------------------------------------------- --}}
    <div class="flex min-h-0 min-w-0 flex-1 flex-col">

        {{-- What the room is reading. --}}
        <div class="relative order-1 flex h-1/2 shrink-0 items-center justify-center overflow-hidden bg-zinc-300 lg:h-auto lg:min-h-0 lg:flex-[2] lg:shrink dark:bg-zinc-900">
            <div
                x-ref="currentBox"
                class="w-full max-h-full max-w-full overflow-hidden bg-white lg:h-full lg:w-auto"
                {{-- Moved exactly as the wall moves it, so that lining the
                     projector up is watched twice over: once across the room
                     and once under the thumb. --}}
                {{-- Bound as an object and never as a string. Alpine writes a string
                     binding with setAttribute('style', …), which throws away the
                     `display: none` x-show had just put on the same element — and the
                     fit is re-read from the screen every second, so a box hidden by
                     x-show came back a beat later and the room's slide was drawn
                     beside the card that had replaced it. The object form sets the
                     properties one by one and leaves the rest of the style alone. --}}
                x-bind:style="{ aspectRatio: aspectRatio, transform: fitTransform, transformOrigin: 'center' }"
                x-bind:class="blanked || openingDark ? 'opacity-25' : ''"
                x-show="!waiting && !showingSplash"
                wire:ignore
            ></div>

            {{-- The title card, as the room is reading it.

                 Drawn here and not merely announced, because the card is what
                 the projector is lined up against and the person doing the
                 lining up is holding this: the fit panel moves both pictures at
                 once, and it is guesswork unless they are the same picture. --}}
            <div
                class="w-full max-h-full max-w-full overflow-hidden bg-gradient-to-br from-zinc-800 via-zinc-900 to-black px-4 lg:h-full lg:w-auto"
                x-bind:style="{ aspectRatio: aspectRatio, transform: fitTransform, transformOrigin: 'center' }"
                x-show="showingSplash"
                x-cloak
            >
                <x-projection-splash-card
                    title-class="text-2xl font-semibold leading-tight tracking-tight text-white/90"
                    caption-class="text-xs text-white/60"
                />
            </div>

            {{-- The thin bar over the letterbox. Everything that is not driving the
                 service lives here, small and out of the thumb's way. --}}
            <div class="absolute inset-x-0 top-0 flex items-center gap-1 bg-gradient-to-b from-black/50 to-transparent px-2 py-1.5 text-white">
                <a href="{{ route('projection-remote.decks') }}" wire:navigate class="shrink-0 rounded p-1" aria-label="{{ __('Decks') }}">
                    <flux:icon.squares-2x2 class="size-5" />
                </a>

                <span class="min-w-0 flex-1 truncate text-xs text-white/80" x-text="title || @js(__('Remote'))"></span>

                {{-- Where the show is on: the walls other than this device, or
                     none. Nothing waits on it — the show can be set up from
                     here before the laptop is on. --}}
                <div class="min-w-0 max-w-[45%] shrink">
                    <livewire:projection.show-status />
                </div>

                <span class="shrink-0 text-xs tabular-nums text-white/80" x-show="total > 0" x-cloak>
                    <span x-text="index + 1"></span>/<span x-text="total"></span>
                </span>

                {{-- Where the picture lands on the wall. Up here with the things
                     that are not driving the service, because it is pressed while
                     the beamer is being lined up and never during a hymn — and
                     lit while the picture is anywhere but where the application
                     would have put it, which is the only warning anybody gets
                     that the deck is not simply centred. --}}
                <button
                    type="button"
                    class="shrink-0 rounded p-1 disabled:opacity-40"
                    x-on:click="openFit()"
                    x-bind:disabled="fitTarget === null"
                    x-bind:class="fitIsNeutral ? '' : 'text-amber-300'"
                    aria-label="{{ __('Fit the picture to the screen') }}"
                >
                    <flux:icon.viewfinder-circle class="size-5" />
                </button>

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
                {{-- What the room is looking at during the opening, and what the
                     next press will do about it. Louder than the rest of this
                     strip on purpose: it is the one state of the remote nobody
                     can work out from the buttons. --}}
                <div class="rounded bg-blue-600/90 px-2 py-0.5 font-medium text-white" x-show="openingHint" x-text="openingHint"></div>
                <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="preparing && !waiting">
                    {{ __('The screen is preparing the deck…') }}
                </div>
                <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="ended && !waiting">
                    {{ __('This projection has been closed on the screen.') }}
                </div>
                <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="busy">
                    {{ __('Drawing the deck…') }}
                </div>
                <div class="rounded bg-black/60 px-2 py-0.5 text-white" x-show="!busy && !waiting && !opening && total === 0">
                    {{ __('This projection has no slides yet.') }}
                </div>
            </div>

            {{-- No show. Nothing is blocked by there being no wall yet: the deck
                 can be put up from here first, and the laptop shows it when it
                 is opened. --}}
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-6 text-center" x-show="waiting" x-cloak>
                <flux:icon.cast class="size-8 text-zinc-500" />
                <div class="text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Nothing is being projected') }}
                </div>
                <flux:button variant="primary" size="sm" icon="play" href="{{ route('projection-remote.decks') }}" wire:navigate>
                    {{ __('Choose a deck') }}
                </flux:button>
                <div class="max-w-xs text-xs text-zinc-500" x-show="walls.length === 0">
                    {{ __('On the computer the room will be reading from, sign in and open the projection screen.') }}
                </div>
            </div>
        </div>

        {{-- What is coming, in the order it is coming, across the phone under the
             slide. The phone's alone: the laptop has the whole deck down its
             right-hand column and the next slide full size under the controls,
             and a third sheet of the same pictures would be one too many. --}}
        <div
            x-ref="strip"
            data-scrolls
            class="order-2 flex h-1/4 shrink-0 touch-pan-x items-center gap-2 overflow-x-auto overflow-y-hidden px-2 py-2 lg:hidden"
            x-bind:style="{ '--slide-ratio': aspectRatio }"
            wire:ignore
        ></div>

        {{-- The thumb: 1 : 1 : 2 across the bottom quarter of the phone, and nothing
             else. On a laptop the same three controls in the same proportions,
             directly under the slide rather than at the foot of the window, each
             naming the key that does what it does. --}}
        <div class="order-3 flex h-1/4 shrink-0 items-stretch gap-2 px-2 pb-2 lg:order-2 lg:h-24 lg:border-y lg:border-zinc-300 lg:py-2 dark:lg:border-zinc-800">
            <button
                type="button"
                class="flex flex-1 flex-col items-center justify-center gap-1 rounded-xl border transition-colors disabled:opacity-40"
                x-on:click="previous()"
                x-bind:class="pressed === 'previous'
                    ? 'border-blue-500 bg-blue-600 text-white ring-4 ring-blue-400/50'
                    : 'border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:active:bg-zinc-800'"
                x-bind:disabled="index === 0"
                aria-label="{{ __('Previous slide') }}"
            >
                <flux:icon.chevron-left class="size-8" />
                <span class="hidden text-xs opacity-60 lg:block" aria-hidden="true">←</span>
            </button>

            <button
                type="button"
                class="flex flex-1 flex-col items-center justify-center gap-1 rounded-xl border transition-colors"
                x-on:click="toggleBlank()"
                x-bind:class="pressed === 'blank'
                    ? 'border-blue-500 bg-blue-600 text-white ring-4 ring-blue-400/50'
                    : blanked
                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                        : 'border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:active:bg-zinc-800'"
                aria-label="{{ __('Blank the screen') }}"
            >
                <flux:icon.eye-slash class="size-7" />
                <span class="hidden text-xs opacity-60 lg:block" aria-hidden="true">B · .</span>
            </button>

            <button
                type="button"
                class="flex flex-[2] flex-col items-center justify-center gap-1 rounded-xl border transition-colors disabled:opacity-40"
                x-on:click="next()"
                x-bind:class="pressed === 'next'
                    ? 'border-blue-500 bg-blue-600 text-white ring-4 ring-blue-400/50'
                    : 'border-zinc-900 bg-zinc-900 text-white active:bg-zinc-700 dark:border-white dark:bg-white dark:text-zinc-900 dark:active:bg-zinc-200'"
                {{-- Not while the opening is being walked: the press that walks it
                     is this one, and a one-slide deck would otherwise have no
                     way out of the card at all. --}}
                x-bind:disabled="!opening && index >= total - 1"
                aria-label="{{ __('Next slide') }}"
            >
                <flux:icon.chevron-right class="size-10" />
                <span class="hidden text-xs opacity-60 lg:block" aria-hidden="true">→ · {{ __('Space') }}</span>
            </button>
        </div>

        {{-- And what the room is about to be reading, in the third of the
             column the slide on the wall leaves it.

             The laptop's alone, and the one thing the cantor at the keyboard
             actually wants under the controls: not a thumbnail of the next verse
             but the next verse, readable, so the hand presses Next knowing what
             lands. Clicking it is pressing Next, because that is what anybody
             looking at it is about to do. --}}
        <button
            type="button"
            class="relative order-4 hidden w-full min-h-0 items-center justify-center overflow-hidden bg-zinc-200 p-1 lg:order-3 lg:flex lg:flex-1 lg:shrink dark:bg-zinc-950"
            x-on:click="next()"
            x-bind:disabled="index >= total - 1"
            aria-label="{{ __('Next slide') }}"
        >
            <div
                x-ref="nextBox"
                class="w-full max-h-full max-w-full overflow-hidden bg-white lg:h-full lg:w-auto"
                x-bind:style="{ aspectRatio: aspectRatio }"
                x-show="index < total - 1"
                wire:ignore
            ></div>

            <span class="absolute bottom-1 start-2 text-[10px] uppercase tracking-wide text-zinc-500" x-show="index < total - 1">
                {{ __('Next') }}
            </span>
        </button>

    </div>{{-- the service --}}

    {{-- ---------------------------------------------------------------
         The laptop's right column: the deck as it was arranged.
         ---------------------------------------------------------------

         Every slide in it, the ones this service walks past included, each with
         the editor's control for taking one out of today's service or putting
         one back. Not on the phone at all: a contact sheet of sixty slides is a
         thing to read ahead in, which is what somebody standing at a laptop is
         doing and what somebody with one thumb free is not. --}}
    <div class="hidden lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:border-s lg:border-zinc-300 lg:bg-zinc-100 xl:w-72 dark:lg:border-zinc-800 dark:lg:bg-zinc-950">
        <div class="flex shrink-0 items-center gap-2 border-b border-zinc-300 px-3 py-2 dark:border-zinc-800">
            <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ __('The deck') }}</span>
            <span class="shrink-0 text-xs tabular-nums text-zinc-500" x-show="total > 0" x-cloak x-text="total"></span>
        </div>

        <div
            x-ref="deck"
            data-scrolls
            class="projection-slides min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3"
            wire:ignore
        ></div>
    </div>

    {{-- ---------------------------------------------------------------
         Where the picture lands on the wall.
         ---------------------------------------------------------------

         The presenter fits a deck into the projector and centres it, which is
         the right answer in every room but one: a 4:3 beamer on a square screen
         hung high, a deck built 1:1 for the glass, and the whole thing still
         landing above the heads it was meant for and clipped at the foot. The
         deck is not wrong — next Sunday's lands identically — so what is nudged
         is the screen, and the setting stays on it, lined up once and still true
         next week.

         It is here rather than at the laptop because of the arithmetic of the
         building: the laptop faces the room from somewhere else, and the only
         person who can see whether the picture is where it should be is the one
         at the organ holding this. Four arrows, two zooms and the way back to
         centred — sized for a thumb, and driven by the same keys on a laptop
         while the panel is open. --}}
    <div class="fixed inset-0 z-20 flex items-center justify-center p-4" x-show="fitOpen" x-cloak>
        <div class="absolute inset-0 bg-black/60" x-on:click="closeFit()"></div>

        <div
            role="dialog"
            aria-modal="true"
            class="relative w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl dark:bg-zinc-900"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="scale-95 opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
        >
            <div class="flex items-center gap-2">
                <span class="flex-1 text-sm font-medium">{{ __('Fit the picture to the screen') }}</span>
                <button type="button" class="rounded p-1" x-on:click="closeFit()" aria-label="{{ __('Close') }}">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            {{-- Which wall, only when there is a choice: two walls on at once is
                 the laptop at home left open, and it is rare. --}}
            <select
                class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-2 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                x-show="walls.length > 1"
                x-on:change="chooseFitScreen($event.target.value)"
                aria-label="{{ __('Which screen') }}"
            >
                <template x-for="screen in walls" x-bind:key="screen.id">
                    <option x-bind:value="screen.id" x-bind:selected="fitTarget && fitTarget.id === screen.id" x-text="screen.label"></option>
                </template>
            </select>
            <p class="mt-1 truncate text-xs text-zinc-500" x-show="walls.length === 1" x-text="fitTarget?.label"></p>

            {{-- The four directions, laid out as the directions they are: a
                 cross a thumb can hit without reading, with the way back to
                 centred in the middle of it, where a thumb that has pushed the
                 picture somewhere silly already is. --}}
            <div class="mx-auto mt-3 grid w-48 grid-cols-3 gap-1.5">
                <div></div>
                <button type="button" class="flex h-14 items-center justify-center rounded-xl border border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:active:bg-zinc-700" x-on:click="moveFit(0, -1)" aria-label="{{ __('Move the picture up') }}">
                    <flux:icon.arrow-up class="size-6" />
                </button>
                <div></div>

                <button type="button" class="flex h-14 items-center justify-center rounded-xl border border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:active:bg-zinc-700" x-on:click="moveFit(-1, 0)" aria-label="{{ __('Move the picture left') }}">
                    <flux:icon.arrow-left class="size-6" />
                </button>

                <button
                    type="button"
                    class="flex h-14 items-center justify-center rounded-xl border border-dashed border-zinc-300 text-zinc-500 disabled:opacity-40 dark:border-zinc-700"
                    x-on:click="resetFit()"
                    x-bind:disabled="fitIsNeutral"
                    aria-label="{{ __('Centre the picture again') }}"
                >
                    <flux:icon.arrow-uturn-left class="size-5" />
                </button>

                <button type="button" class="flex h-14 items-center justify-center rounded-xl border border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:active:bg-zinc-700" x-on:click="moveFit(1, 0)" aria-label="{{ __('Move the picture right') }}">
                    <flux:icon.arrow-right class="size-6" />
                </button>

                <div></div>
                <button type="button" class="flex h-14 items-center justify-center rounded-xl border border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:active:bg-zinc-700" x-on:click="moveFit(0, 1)" aria-label="{{ __('Move the picture down') }}">
                    <flux:icon.arrow-down class="size-6" />
                </button>
                <div></div>
            </div>

            {{-- And how large it is, said as a percentage: a number the cantor
                 can read back to somebody standing at the projector. --}}
            <div class="mt-3 flex items-center justify-center gap-3">
                <button type="button" class="flex size-14 items-center justify-center rounded-xl border border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:active:bg-zinc-700" x-on:click="zoomFit(-1)" aria-label="{{ __('Smaller') }}">
                    <flux:icon.magnifying-glass-minus class="size-6" />
                </button>

                <span class="min-w-16 text-center text-lg font-medium tabular-nums" x-text="`${fitPercent}%`"></span>

                <button type="button" class="flex size-14 items-center justify-center rounded-xl border border-zinc-300 bg-white active:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:active:bg-zinc-700" x-on:click="zoomFit(1)" aria-label="{{ __('Larger') }}">
                    <flux:icon.magnifying-glass-plus class="size-6" />
                </button>
            </div>

            <p class="mt-3 text-center text-xs text-zinc-500">
                {{ __('This stays with the screen, not with the deck.') }}
            </p>

            {{-- The laptop beside the projector drives the same four arrows from
                 the keyboard while this is open. --}}
            <p class="mt-1 hidden text-center text-xs text-zinc-400 lg:block">
                {{ __('Arrows move · + and − scale · 0 centres · Esc closes') }}
            </p>
        </div>
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
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs"
                    x-show="moveUrl"
                    x-on:click="toggleReorder()"
                    x-bind:class="reorder ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500'"
                    x-bind:aria-pressed="reorder ? 'true' : 'false'"
                >
                    <flux:icon.arrows-up-down class="size-4" />
                    {{ __('Reorder') }}
                </button>
                <button type="button" class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-amber-700 dark:text-amber-300" x-show="addedMusicsUrl" x-on:click="openAddMusic(null)">
                    <flux:icon.plus class="size-4" />
                    {{ __('Add music') }}
                </button>
                <button type="button" class="rounded p-1" x-on:click="closeList()" aria-label="{{ __('Close') }}">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            <div class="min-h-0 flex-1 touch-pan-y space-y-2 overflow-y-auto px-3 py-3">
                {{-- Every slot the plan gives this service, whether or not the
                     deck has taken anything from it yet — a slot nobody has
                     touched still has a place here, said plainly rather than
                     left for the cantor to wonder whether it was forgotten. --}}
                <template x-for="band in outline" x-bind:key="band.key">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-1" x-show="band.name">
                            <span class="min-w-0 flex-1 truncate text-xs font-semibold uppercase tracking-wide text-zinc-400" x-text="band.name"></span>
                            <template x-if="reorder && band.moves">
                                <span class="flex shrink-0 gap-1">
                                    <button type="button" class="rounded-lg border border-zinc-200 p-1.5 disabled:opacity-30 dark:border-zinc-700" x-bind:disabled="!band.moves.up" x-on:click="move('slot', band.slotId, -1)" aria-label="{{ __('Move this slot up') }}"><flux:icon.chevron-up class="size-4" /></button>
                                    <button type="button" class="rounded-lg border border-zinc-200 p-1.5 disabled:opacity-30 dark:border-zinc-700" x-bind:disabled="!band.moves.down" x-on:click="move('slot', band.slotId, 1)" aria-label="{{ __('Move this slot down') }}"><flux:icon.chevron-down class="size-4" /></button>
                                </span>
                            </template>
                            <button type="button" class="shrink-0 rounded-lg p-1.5 text-amber-600 dark:text-amber-300" x-show="addedMusicsUrl && band.slotId" x-on:click="openAddMusic(band.slotId)" aria-label="{{ __('Add music to this slot, only in this projection') }}">
                                <flux:icon.plus class="size-4" />
                            </button>
                        </div>

                        <template x-if="band.blocks.length === 0">
                            <div class="rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm italic text-zinc-400 dark:border-zinc-600">
                                {{ __('Nothing chosen yet') }}
                            </div>
                        </template>

                        <template x-for="block in band.blocks" x-bind:key="block.key">
                            <div class="space-y-1.5" x-bind:class="block.local ? 'border-s-2 border-dashed border-amber-500 ps-2' : ''">
                                <div class="flex items-center gap-1" x-show="block.kind === 'music' && block.name">
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-500" x-text="block.name"></span>
                                    <template x-if="reorder && block.kind === 'music'">
                                        <span class="flex shrink-0 gap-1">
                                            <button type="button" class="rounded-lg border border-zinc-200 p-1.5 disabled:opacity-30 dark:border-zinc-700" x-bind:disabled="!block.moves.up" x-on:click="move(block.moveKind, block.moveId, -1)" aria-label="{{ __('Move this music up') }}"><flux:icon.chevron-up class="size-4" /></button>
                                            <button type="button" class="rounded-lg border border-zinc-200 p-1.5 disabled:opacity-30 dark:border-zinc-700" x-bind:disabled="!block.moves.down" x-on:click="move(block.moveKind, block.moveId, 1)" aria-label="{{ __('Move this music down') }}"><flux:icon.chevron-down class="size-4" /></button>
                                        </span>
                                    </template>
                                    <button type="button" class="shrink-0 rounded-lg p-1.5 text-zinc-400" x-show="block.local" x-on:click="removeAddedMusic(block.addedMusicId, block.rows.length)" aria-label="{{ __('Remove this music from the projection') }}">
                                        <flux:icon.trash class="size-4" />
                                    </button>
                                </div>
                                <div class="text-xs text-amber-700 dark:text-amber-300" x-show="block.local">{{ __('Only in this projection') }}</div>

                                {{-- Each row named the way the cantor asked for it: the
                                     score, the slot it stands in — never "slide 7", which
                                     is the one thing about a row that nobody is looking
                                     for. A lone row (a screen of words, a score chosen
                                     outside the plan) is the same card without a music
                                     heading above it. --}}
                                <template x-for="row in (block.kind === 'music' ? block.rows : [block.row])" x-bind:key="row.id">
                                    <div
                                        class="rounded-lg border px-3 py-2"
                                        x-bind:class="row.current
                                            ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-800'
                                            : 'border-zinc-200 dark:border-zinc-700'"
                                    >
                                        <div class="flex items-start gap-1">
                                            <button type="button" class="block min-w-0 flex-1 text-start" x-on:click="reorder || (goToEntry(row.id), closeList())">
                                                <div class="truncate text-xs uppercase tracking-wide text-zinc-400" x-show="block.kind === 'row' && row.slot" x-text="row.slot"></div>

                                                <div
                                                    class="truncate text-sm text-zinc-600 dark:text-zinc-300"
                                                    x-bind:class="row.isText ? 'italic' : ''"
                                                    {{-- A score that names itself after its music says it once. --}}
                                                    x-show="rowName(row) && rowName(row) !== block.name"
                                                    x-text="rowName(row)"
                                                ></div>
                                                <div class="truncate text-xs text-zinc-500" x-show="row.variationName" x-text="row.variationName"></div>
                                                <div class="truncate text-xs text-zinc-500" x-show="row.reference" x-text="row.reference"></div>

                                                <img
                                                    class="mt-1 max-h-20 max-w-full rounded bg-white object-contain"
                                                    x-show="row.incipit"
                                                    x-bind:src="row.incipit"
                                                    alt=""
                                                    loading="lazy"
                                                />
                                            </button>

                                            {{-- Taking a whole engraving out of today's deck — a
                                                 text row has no score behind it and nothing here
                                                 to take out. --}}
                                            <template x-if="reorder">
                                                <span class="flex shrink-0 flex-col gap-1">
                                                    <button type="button" class="rounded-lg border border-zinc-200 p-1.5 disabled:opacity-30 dark:border-zinc-700" x-bind:disabled="!row.moves.up" x-on:click="move('entry', row.id, -1)" aria-label="{{ __('Move up') }}"><flux:icon.chevron-up class="size-4" /></button>
                                                    <button type="button" class="rounded-lg border border-zinc-200 p-1.5 disabled:opacity-30 dark:border-zinc-700" x-bind:disabled="!row.moves.down" x-on:click="move('entry', row.id, 1)" aria-label="{{ __('Move down') }}"><flux:icon.chevron-down class="size-4" /></button>
                                                </span>
                                            </template>

                                            <button
                                                type="button"
                                                class="shrink-0 rounded p-1 text-zinc-400"
                                                x-show="row.scoreId"
                                                x-on:click="toggleScore(row.scoreId, row.assignmentId, row.fileId, row.addedMusicId)"
                                                x-bind:aria-label="removeScoreText"
                                            >
                                                <flux:icon.minus-circle class="size-5" />
                                            </button>
                                        </div>

                                        <div class="mt-1.5 flex flex-wrap gap-1">
                                            <template x-for="slide in row.slides" x-bind:key="slide.index">
                                                <div class="flex items-center">
                                                    {{-- A slide today's service shows: a number to jump to. --}}
                                                    <button
                                                        type="button"
                                                        class="min-w-8 rounded px-2 py-1 text-xs font-medium transition-colors"
                                                        x-show="slide.shown"
                                                        x-on:click="goToSlide(row.id, slide.index); closeList()"
                                                        x-bind:class="slide.current
                                                            ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900'
                                                            : (slide.deviates
                                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'
                                                                : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300')"
                                                        x-text="slide.index + 1"
                                                    ></button>

                                                    {{-- One today leaves out — the deck's own
                                                         arrangement, or a verse taken out from the
                                                         pane beside this. Bringing it back is today's
                                                         deviation and not an edit: the projection
                                                         stays as its author arranged it, and the
                                                         slide was engraved at load like every other,
                                                         so this costs nothing. --}}
                                                    <button
                                                        type="button"
                                                        class="min-w-8 rounded border border-dashed border-zinc-300 px-2 py-1 text-xs text-zinc-400 dark:border-zinc-600"
                                                        x-show="!slide.shown"
                                                        x-on:click="toggleReveal(row.id, slide.index)"
                                                        x-bind:aria-label="'{{ __('Bring this verse back for today') }}'"
                                                        x-text="slide.index + 1"
                                                    ></button>

                                                    {{-- And putting it away again. --}}
                                                    <button
                                                        type="button"
                                                        class="px-1 text-xs text-amber-600 dark:text-amber-300"
                                                        x-show="slide.shown && slide.deviates"
                                                        x-on:click="toggleReveal(row.id, slide.index)"
                                                        x-bind:aria-label="'{{ __('Leave this verse out again') }}'"
                                                    >&times;</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="block.kind === 'music' && block.rows.length === 0">
                                    <div class="rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm italic text-zinc-400 dark:border-zinc-600">
                                        {{ __('Not sung from yet') }}
                                    </div>
                                </template>

                                {{-- What the music could still be sung from: every
                                     other engraving the plan offers, one tap from
                                     standing beside the rows above it. --}}
                                <template x-for="offer in (block.kind === 'music' ? block.offers : [])" x-bind:key="`offer-${offer.scoreId}-${offer.fileId ?? 0}`">
                                    <button
                                        type="button"
                                        class="block w-full rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-start disabled:opacity-40 dark:border-zinc-600"
                                        x-on:click="toggleScore(offer.scoreId, block.assignmentId, offer.fileId, block.addedMusicId)"
                                        x-bind:disabled="offer.inBooklets === false"
                                    >
                                        <span class="flex items-center gap-1.5 text-sm text-zinc-500">
                                            <flux:icon.plus class="size-3.5 shrink-0" />
                                            <span class="min-w-0 flex-1 truncate" x-text="offer.title"></span>
                                        </span>
                                        <img
                                            class="mt-1 max-h-20 max-w-full rounded bg-white object-contain opacity-50"
                                            x-show="offer.incipitUrl"
                                            x-bind:src="offer.incipitUrl"
                                            alt=""
                                            loading="lazy"
                                        />
                                    </button>
                                </template>
                            </div>
                        </template>
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
                    x-on:click="confirm(clearText) && clearScreen()"
                >
                    {{ __('Take the deck down') }}
                </flux:button>
            </div>
        </div>
    </div>
    {{-- ---------------------------------------------------------------
         A music the plan does not have.
         ---------------------------------------------------------------

         The same music search the editor uses — filters, collections, the
         quick create — since it already lays itself out for a phone. What is
         picked goes to the end of the slot it was asked for from, or to the
         end of the deck, and is moved into place with Reorder — then its score
         is chosen from the offers like any other music's. Above the plan sheet,
         since it is opened from there. Lazy, so a service nobody adds music to
         never loads it. --}}
    <div
        class="fixed inset-0 z-30 flex items-end justify-center lg:items-center"
        x-show="addMusicOpen"
        x-cloak
        x-on:music-selected-remote.window="addMusic($event.detail.musicId)"
    >
        <div class="absolute inset-0 bg-black/50" x-on:click="closeAddMusic()"></div>

        <div
            role="dialog"
            aria-modal="true"
            class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-t-2xl bg-white shadow-xl lg:rounded-2xl dark:bg-zinc-900"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full lg:translate-y-0 lg:scale-95 lg:opacity-0"
            x-transition:enter-end="translate-y-0 lg:scale-100 lg:opacity-100"
        >
            <div class="flex shrink-0 items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium">{{ __('Add music') }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Only this projection holds it; the plan stays as it is.') }}</div>
                </div>
                <button type="button" class="rounded p-1" x-on:click="closeAddMusic()" aria-label="{{ __('Close') }}">
                    <flux:icon.x-mark class="size-5" />
                </button>
            </div>

            <div data-scrolls class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 select-text">
                <livewire:music-search lazy selectable="true" source="-remote" wire:key="projection-remote-music-search" />
            </div>
        </div>
    </div>
</div>
