<?php

namespace App\Livewire\Pages;

use App\Models\Folder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;
use Livewire\WithPagination;

class Folders extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Folder::class);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('My Folders'),
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(Folder $folder): void
    {
        $this->authorize('delete', $folder);

        $folder->delete();

        $this->dispatch('toast', message: __('Folder deleted.'), type: 'success');
    }

    public function render(): IlluminateView
    {
        $search = trim($this->search);

        $folders = Folder::query()
            ->mine(Auth::user())
            ->withCount('scores')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%");
            })
            ->latest('updated_at')
            ->paginate(10);

        return view('livewire.pages.folders', [
            'folders' => $folders,
        ]);
    }
}
