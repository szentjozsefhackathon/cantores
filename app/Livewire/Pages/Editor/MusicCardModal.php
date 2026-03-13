<?php

namespace App\Livewire\Pages\Editor;

use Livewire\Attributes\On;
use Livewire\Component;

class MusicCardModal extends Component
{
    public ?int $musicId = null;

    #[On('show-music-card-modal')]
    public function open(int $musicId): void
    {
        $this->musicId = $musicId;
    }

    public function close(): void
    {
        $this->musicId = null;
    }

    public function render()
    {
        return view('livewire.pages.editor.music-card-modal');
    }
}
