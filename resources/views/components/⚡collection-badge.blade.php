<?php

use App\Models\Collection;
use Livewire\Component;

new class extends Component
{
    public Collection $collection;

    public function mount(Collection $collection): void
    {
        $this->collection = $collection;
    }

}
?>

<flux:tooltip content="{{ $collection->title }} {{ $collection->pivot->page_number ? __('(p.:page)', ['page' => $collection->pivot->page_number]) : '' }}">
<flux:badge size="sm">{{ $collection->abbreviation ?? $collection->title }} {{ $collection->pivot->order_number }}</flux:badge>
</flux:tooltip>