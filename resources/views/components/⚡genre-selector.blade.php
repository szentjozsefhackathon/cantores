<?php

use App\Facades\GenreContext;
use App\Models\Genre;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedGenreId = null;

    public function mount(): void
    {
        $this->selectedGenreId = GenreContext::getId();
    }

    public function genres()
    {
        return Genre::all();
    }

    public function updatedSelectedGenreId($value): void
    {
        // Convert empty string to null
        if ($value === '') {
            $value = null;
        }

        GenreContext::set($value);

        // Dispatch event to notify other components
        $this->dispatch('genre-changed', genreId: $value);
    }
}
?>

<div class="flex items-center justify-center">
    <flux:radio.group wire:model.live="selectedGenreId" variant="segmented">
            @if (is_null($this->selectedGenreId))
                <flux:radio label="Mind" value="" checked />
            @else
                <flux:radio label="Mind" value="" />
            @endif
            @foreach($this->genres() as $genre)
                <flux:radio value="{{ $genre->id }}" icon="{{ $genre->icon() }}" />
            @endforeach
        </flux:radio.group>
</div>
