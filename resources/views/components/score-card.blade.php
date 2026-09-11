@props([
    'score',
    'href',
    'incipitSrc' => null,
    'meta' => null,
    'actions' => null,
])

{{--
    One score as a card: the incipit first, because that is what a musician
    recognises a score by, then its title and whatever the page wants to say
    about it.
--}}
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border border-zinc-200 p-3 transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800']) }}>
    @php
        $incipit = $incipitSrc ?? ($score->hasIncipit() ? $score->incipitUrl() : null);
    @endphp
    @if($incipit)
        <x-incipit-image :src="$incipit"
            :alt="$score->title"
            img-class="h-auto max-h-12 w-auto max-w-[160px]" />
    @else
        <span class="flex size-12 shrink-0 items-center justify-center rounded bg-zinc-200 dark:bg-zinc-700">
            <flux:icon name="musical-note" class="size-5 text-zinc-400" />
        </span>
    @endif
    <div class="min-w-0 flex-1 space-y-1">
        <div>
            <a href="{{ $href }}" wire:navigate class="break-words font-medium hover:underline">{{ $score->title }}</a>
            <x-score-variation-name :score="$score" />
        </div>
        @if($meta)
            <div class="flex flex-wrap items-center gap-2">{{ $meta }}</div>
        @endif
    </div>
    @if($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endif
</div>
