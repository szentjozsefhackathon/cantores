<?php

namespace App\Livewire\Pages\Editor;

use App\Models\Music;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

class MusicAuditModal extends Component
{
    use AuthorizesRequests;

    public bool $show = false;

    public ?int $musicId = null;

    #[On('show-music-audit-log')]
    public function open(int $musicId): void
    {
        $music = Music::findOrFail($musicId);
        $this->authorize('view', $music);

        $this->musicId = $music->id;
        $this->show = true;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $music = $this->musicId ? Music::find($this->musicId) : null;

        $audits = $music
            ? $music->audits()->with('user')->latest()->get()
            : collect();

        return view('livewire.pages.editor.music-audit-modal', [
            'music' => $music,
            'audits' => $audits,
        ]);
    }
}
