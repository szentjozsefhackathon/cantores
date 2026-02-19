<?php

use App\Models\Genre;
use App\Models\MusicAssignmentFlag;
use App\Models\MusicPlan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public MusicPlan $musicPlan;

    // ✅ This is the bindable property
    #[Modelable]
    public ?int $genreId = null;

    public bool $isEditingGenre = false;

    public function mount(MusicPlan $musicPlan)
    {
        $this->musicPlan = $musicPlan;
        // initialize from DB
        $this->genreId = $musicPlan->genre_id;

    }

    public function toggleGenreEditing()
    {
        $this->isEditingGenre = !$this->isEditingGenre;
        $this->genreId = $this->musicPlan->genre_id;
    }

    public function saveGenre()
    {
        $this->musicPlan->genre_id = $this->genreId;
        $this->musicPlan->save();
        $this->musicPlan->unsetRelation('genre');
        $this->isEditingGenre = false;
    }

    // ✅ Use Eloquent so label/name methods work
    public function getGenresProperty()
    {
        return Genre::allCached()->sortBy('name');
    }
         
    
}

?>

<div>
    <flux:heading size="sm" class="text-neutral-600 dark:text-neutral-400 mb-1">Műfaj</flux:heading>
    @if($isEditingGenre)
    <div class="space-y-2">
        <flux:field>
            <flux:select wire:model.live="genreId">
                <flux:select.option value="">– Nincs műfaj –</flux:select.option>
                @foreach($this->genres as $genre)
                <flux:select.option value="{{ $genre->id }}">{{ $genre->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <div class="flex gap-2">
            <flux:button
                wire:click="saveGenre"
                icon="check"
                variant="primary"
                size="xs">
                Mentés
            </flux:button>
            <flux:button
                wire:click="toggleGenreEditing"
                icon="x-mark"
                variant="outline"
                size="xs">
                Mégse
            </flux:button>
        </div>
    </div>
    @else
    <div class="flex items-center gap-2">
        <flux:text class="text-base font-semibold">
            {{ $this->genreId ? $this->genres->firstWhere('id', $genreId)?->label() : '–' }}
        </flux:text>
        <flux:button
            wire:click="toggleGenreEditing"
            icon="pencil"
            variant="outline"
            size="xs"
            title="Műfaj szerkesztése" />
    </div>
    @endif
</div>