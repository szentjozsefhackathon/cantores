@props(['collection', 'tooltip' => true])

{{--
    `tooltip => false` swaps the Flux tooltip for a plain `title`. A Flux tooltip is a
    custom element with its own listeners, and a listing that repeats badges dozens of
    times pays for every one of them; the native tooltip carries the same text for free.
--}}
<div>
@can('view', $collection)
    @php
        $label = trim($collection->title.' '.($collection->pivot->page_number ? __('(p.:page)', ['page' => $collection->pivot->page_number]) : ''));
    @endphp
    @if($tooltip)
        <flux:tooltip content="{{ $label }}">
        <flux:badge size="sm" class="relative z-10">{{ $collection->abbreviation ?? $collection->title }} {{ $collection->pivot->order_number }}</flux:badge>
        </flux:tooltip>
    @else
        <flux:badge size="sm" class="relative z-10" title="{{ $label }}">{{ $collection->abbreviation ?? $collection->title }} {{ $collection->pivot->order_number }}</flux:badge>
    @endif
@endcan
</div>
