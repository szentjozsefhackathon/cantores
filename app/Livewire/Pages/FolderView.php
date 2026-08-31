<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use App\Models\Share;
use App\Services\ShareAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

class FolderView extends Component
{
    public ?Folder $folder = null;

    public string $name = '';

    public string $shareToken = '';

    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Score> */
    public $scores;

    public function mount(string $token): void
    {
        $shareAccess = app(ShareAccessService::class);

        $share = $shareAccess->resolveOfType($token, Folder::class);
        abort_if(! $share instanceof Share, 404);

        /** @var Folder $folder */
        $folder = $share->shareable;

        if (Auth::check() && Auth::id() === $folder->user_id) {
            $this->redirectRoute('folders.edit', ['folder' => $folder->id], navigate: true);

            return;
        }

        $share->touchLastViewed();

        $this->folder = $folder;
        $this->name = $folder->name;
        $this->shareToken = $token;
        $this->scores = $folder->scores()->with('music')->orderBy('title')->get();
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
