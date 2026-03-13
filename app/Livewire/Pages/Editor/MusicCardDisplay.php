<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Music;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MusicCardDisplay extends Component
{
    public int $musicId;

    public function render(): View
    {
        $music = Music::findOrFail($this->musicId);

        return view('livewire.pages.editor.music-card-display', [
            'music' => $music,
        ]);
    }
}
