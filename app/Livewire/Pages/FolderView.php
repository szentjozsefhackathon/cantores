<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class FolderView extends Component
{
    public ?Folder $folder = null;

    public string $name = '';

    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Score> */
    public $scores;

    public function mount(string $token): void
    {
        $folder = Folder::query()->where('share_token', $token)->first();
        abort_if($folder === null, 404);

        if (Auth::check() && Auth::id() === $folder->user_id) {
            $this->redirectRoute('folders.edit', ['folder' => $folder->id], navigate: true);

            return;
        }

        $this->folder = $folder;
        $this->name = $folder->name;
        $this->scores = $folder->scores()->orderBy('title')->get();
    }

    public function rendering(IlluminateView $view): void
    {
        if (! $this->folder instanceof Folder) {
            return;
        }

        $view->layout('layouts::app.main', [
            'title' => $this->folder->name,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.folder-view');
    }
}
