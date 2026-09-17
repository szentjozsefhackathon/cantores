@props([
    'titleClass' => 'text-[7vmin] font-semibold leading-tight tracking-tight text-white/90',
    'captionClass' => 'text-[2.5vmin] text-white/60',
])

{{--
    The title card shown on both the projection screen and the remote while a
    deck is up but nothing is being read yet. One partial for both, so the
    room and the phone in the cantor's hand always agree on what "ready"
    looks like.

    The deck's name wraps rather than truncating: a cut-off title still reads
    as ready, and the person lining up the beamer against this card has no
    way to know part of it is missing.
--}}
<div {{ $attributes->merge(['class' => 'flex h-full w-full flex-col items-center justify-center gap-4 text-center']) }}>
    <div class="max-w-[80%] whitespace-normal break-words {{ $titleClass }}" x-text="title"></div>

    <div class="{{ $captionClass }}">Cantores.hu</div>
</div>
