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
        return Genre::allCached();
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

<div class="inline-flex items-center">
    <flux:radio.group wire:model.live="selectedGenreId" variant="segmented">
            <flux:tooltip :content="__('All')" class="flex flex-1">
                <flux:radio label="{{ __('All') }}" value="" class="transition-colors hover:bg-zinc-800/5 dark:hover:bg-white/10" :checked="is_null($this->selectedGenreId)" />
            </flux:tooltip>
            @foreach($this->genres() as $genre)
                <flux:tooltip :content="$genre->label()" class="flex flex-1">
                    <flux:radio value="{{ $genre->id }}" icon="{{ $genre->icon() }}" class="transition-colors hover:bg-zinc-800/5 dark:hover:bg-white/10" />
                </flux:tooltip>
            @endforeach
        </flux:radio.group>
</div>
