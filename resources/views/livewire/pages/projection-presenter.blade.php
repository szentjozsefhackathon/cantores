@push('page-bundles')
resources/js/projection-presenter.js
@endpush

{{-- The deck on the wall.

     One slide, as large as the screen will take it, on black. Everything else is
     a control that hides itself: in a window the bar at the top fades out while
     nobody moves the mouse, and in full screen — where this page is what the
     congregation is looking at — no control appears at all, because a projector
     showing a toolbar during the Sanctus is a projector showing the wrong thing.

     The whole deck is engraved once, when the page loads, and then only swapped
     between. Re-engraving a score mid-service — abc2svg and exsurge both block
     the browser for a moment — would be a stutter at exactly the wrong time. --}}
<div
    x-ref="stage"
    data-projection-presenter
    class="fixed inset-0 z-50 bg-black"
    data-projection-config="{{ json_encode([
        'geometry' => $geometry,
        'entries' => $entries,
        'excluded' => $excluded,
        // Where this screen and the phone agree about the service. Plain JSON
        // endpoints rather than this component: the stage below is wire:ignore'd
        // so that no re-render can touch the picture mid-service, and polling
        // Livewire would give that up.
        //
        // The screen's own URL is the one constant here; the presentation's two
        // are not, because a screen outlives the decks put on it and the phone
        // may point this one somewhere else mid-service. They are baked in only
        // for the deck this page was opened on, and afterwards come from the
        // screen's answer.
        'revision' => $revision,
        'title' => $title,
        'screenUrl' => route('screens.state', ['screen' => $screen->id]),
        'presentationId' => $presentation?->id,
        'stateUrl' => $presentation === null ? null : route('presentations.state', ['presentation' => $presentation->id]),
        'payloadUrl' => $presentation === null ? null : route('presentations.payload', ['presentation' => $presentation->id]),
        'csrfToken' => csrf_token(),
    ]) }}"
    x-data="projectionPresenter(JSON.parse($el.dataset.projectionConfig))"
    x-on:projection-updated.window="applyUpdate($event.detail)"
    x-on:keydown.window="onKey($event)"
    x-on:mousemove.window="wake()"
    x-on:fullscreenchange.window="syncFullscreen()"
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

    {{-- The controls. Out of the way of the picture, and out of sight when the
         room has settled. --}}
    <div
        class="absolute inset-x-0 top-0 z-10 flex items-center gap-2 bg-gradient-to-b from-black/80 to-transparent px-4 py-3 transition-opacity duration-500"
        x-bind:class="controlsHidden ? 'pointer-events-none opacity-0' : 'opacity-100'"
    >
        @if($projection !== null)
            <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('projections.edit', ['projection' => $projection->id]) }}" class="!text-white">
                {{ __('Back to the editor') }}
            </flux:button>
        @else
            <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('plan-documents') }}" class="!text-white" wire:navigate>
                {{ __('Booklets & Projections') }}
            </flux:button>
        @endif

        {{-- The deck's title follows the screen, because the screen may be
             pointed at another one without this page reloading. --}}
        <span class="truncate text-sm text-white/70" x-text="title || @js(__('Projection screen'))"></span>

        <div class="ms-auto flex items-center gap-2">
            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-medium text-white/80" x-show="total > 0" x-cloak>
                <span x-text="index + 1"></span> / <span x-text="total"></span>
            </span>

            @if($projection !== null)
                <flux:tooltip :content="__('Read the projection again')">
                    <flux:button size="sm" variant="ghost" icon="arrow-path" :aria-label="__('Read the projection again')" wire:click="reload" class="!text-white" />
                </flux:tooltip>
            @endif

            <flux:tooltip :content="__('Full screen')">
                <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" :aria-label="__('Full screen')" x-on:click="toggleFullscreen()" class="!text-white" />
            </flux:tooltip>
        </div>
    </div>

    {{-- The slide itself. The white box is the shape of the screen the deck was
         built for, fitted into whatever shape the projector actually is — so a
         16:9 deck on a 4:3 beamer is letterboxed rather than stretched. --}}
    <div class="absolute inset-0 flex items-center justify-center" x-on:click="next()">
        <div
            x-ref="stageBox"
            class="bg-white"
            x-show="!waiting"
            x-bind:style="`aspect-ratio: ${aspectRatio}; height: 100%; max-width: 100%; max-height: 100%;`"
            wire:ignore
        ></div>
    </div>

    {{-- Nothing at all: the key a cantor presses when the sermon starts, and
         also the moment a deck is being swapped for another. The two are one
         picture and two states — blanking is an instruction only the cantor
         takes back, and preparing clears itself when the drawing is done.

         It arrives over the picture rather than instead of it, so that going out
         can be a fade and coming back cannot: the duration is part of the state,
         and is zero in every direction except the cantor blanking the wall. --}}
    <div
        class="pointer-events-none absolute inset-0 bg-black"
        style="opacity: 0"
        x-bind:style="`opacity: ${dark ? 1 : 0}; transition: opacity ${darkFadeMs}ms ease-in;`"
    ></div>

    <div
        class="absolute inset-x-0 bottom-0 z-10 flex items-center justify-center gap-3 bg-gradient-to-t from-black/80 to-transparent px-4 py-3 text-xs text-white/60 transition-opacity duration-500"
        x-bind:class="controlsHidden ? 'pointer-events-none opacity-0' : 'opacity-100'"
    >
        <span>{{ __('Space / → next · ← back · B blank · F full screen · Esc exit') }}</span>

        {{-- Only while the wall is behind an edit somebody has just saved. It is
             the same comparison the phone shows, and it is here so that the
             person at the keyboard is not the last to know. --}}
        <span x-show="serverRevision !== drawnRevision" x-cloak class="text-amber-300/80">
            {{ __('Reading the deck again…') }}
        </span>
    </div>

    <div class="absolute inset-0 flex items-center justify-center text-sm text-white/50" x-show="!waiting && !preparing && total === 0 && !busy" x-cloak>
        {{ __('This projection has no slides yet.') }}
    </div>

    {{-- The parish laptop at the start of Mass: put in front of the room, signed
         in, and not touched again. What it says is the one thing the phone
         cannot say for it — that this screen is here and waiting to be given
         something. --}}
    <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 px-8 text-center" x-show="waiting" x-cloak>
        <flux:icon.tv class="size-10 text-white/30" />
        <div class="text-lg text-white/70">{{ __('This screen is waiting for a deck.') }}</div>
        <div class="max-w-md text-sm text-white/40">
            {{ __('Choose one from the remote on your phone, and it will appear here.') }}
        </div>
    </div>

    {{-- Between two decks. Deliberately quiet: the room is looking at black and
         does not need to be told why, but the person at the laptop does. --}}
    <div class="absolute inset-x-0 bottom-16 flex justify-center text-sm text-white/40" x-show="preparing" x-cloak>
        {{ __('Preparing the deck…') }}
    </div>
</div>
