<?php

use App\Models\Genre;
use Livewire\Component;

new class extends Component
{

    public ?int $genreId = null;

    public function getIconProperty(): string
    {
        if (! $this->genreId) return 'musical-note';

        return Genre::findCached($this->genreId)?->icon() ?? 'musical-note';
    }
};
?>

<div>
    @switch($this->icon)
        @case('organist')
            <flux:icon name="organist" class="h-10 w-10" />
            @break
        @case('guitar')
            <flux:icon name="guitar" class="h-10 w-10" />
            @break
        @case('other')
            <flux:icon name="genre_other" />
            @break
        @default
            <flux:icon name="musical-note" class="h-10 w-10" variant="outline" />
    @endswitch
</div>
