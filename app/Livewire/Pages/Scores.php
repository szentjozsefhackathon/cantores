<?php

namespace App\Livewire\Pages;

use App\Enums\ScoreFormat;
use App\Models\Score;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;
use Livewire\WithPagination;

class Scores extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Score::class);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', [
            'title' => __('My Scores'),
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(Score $score): void
    {
        $this->authorize('delete', $score);

        $score->delete();

        $this->dispatch('toast', message: __('Score deleted.'), type: 'success');
    }

    public function render()
    {
        $search = trim($this->search);

        $scores = Score::query()
            ->mine(Auth::user())
            ->with(['music', 'folders'])
            ->withCount('liveShares')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'ilike', "%{$search}%")
                        ->orWhereHas('music', fn ($query) => $query->where('title', 'ilike', "%{$search}%"));
                });
            })
            ->latest('updated_at')
            ->paginate(10);

        return view('livewire.pages.scores', [
            'scores' => $scores,
            'formats' => ScoreFormat::cases(),
        ]);
    }
}
