@props(['collection'])

<div>
@can('view', $collection)
    <flux:tooltip content="{{ $collection->title }} {{ $collection->pivot->page_number ? __('(p.:page)', ['page' => $collection->pivot->page_number]) : '' }}">
    <flux:badge size="sm">{{ $collection->abbreviation ?? $collection->title }} {{ $collection->pivot->order_number }}</flux:badge>    
    </flux:tooltip>    
@endcan
</div>