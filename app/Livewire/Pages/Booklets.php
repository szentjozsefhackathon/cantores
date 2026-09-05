<?php

namespace App\Livewire\Pages;

use App\Models\Booklet;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;
use Livewire\WithPagination;

class Booklets extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Booklet::class);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('My Booklets'),
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(Booklet $booklet): void
    {
        $this->authorize('delete', $booklet);

        $booklet->delete();

        $this->dispatch('toast', message: __('Booklet deleted.'), type: 'success');
    }

    public function render(): IlluminateView
    {
        $search = trim($this->search);

        $booklets = Booklet::query()
            ->mine(Auth::user())
            ->withCount('entries')
            ->with('musicPlan.celebration')
            ->when($search !== '', fn ($query) => $query->where('title', 'ilike', "%{$search}%"))
            ->latest('updated_at')
            ->paginate(10);

        return view('livewire.pages.booklets', [
            'booklets' => $booklets,
        ]);
    }
}
