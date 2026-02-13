<?php

namespace App\Livewire\Pages;

use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MusicPlans extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        //
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getMusicPlansQuery()
    {
        $query = MusicPlan::query()
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('celebration_name', 'ilike', "%{$this->search}%")
                    ->orWhere('season_text', 'ilike', "%{$this->search}%")
                    ->orWhere('year_letter', 'ilike', "%{$this->search}%");
            });
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.pages.music-plans', [
            'musicPlans' => $this->getMusicPlansQuery()->paginate(12),
        ]);
    }
}
