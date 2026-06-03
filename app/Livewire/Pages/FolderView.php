<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\Score;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
        $this->scores = $folder->scores()->with('music')->orderBy('title')->get();

        $this->scores->each(function (Score $score): void {
            if ($score->share_token !== null) {
                return;
            }

            do {
                $shareToken = Str::random(32);
            } while (Score::query()->where('share_token', $shareToken)->exists());

            $score->share_token = $shareToken;
            $score->save();
        });
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
