<?php

namespace App\Livewire\Pages;

use App\Facades\RealmContext;
use App\Models\MusicPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MyMusicPlans extends Component
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

    #[On('realm-changed')]
    public function onRealmChanged(): void
    {
        $this->resetPage();
    }

    public function getMusicPlansQuery()
    {
        $query = MusicPlan::query()
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        // Filter by current realm
        $realmId = RealmContext::getId();
        if ($realmId !== null) {
            // Show plans that belong to the current realm OR have no realm (belongs to all)
            $query->where(function ($q) use ($realmId) {
                $q->whereNull('realm_id')
                    ->orWhere('realm_id', $realmId);
            });
        }
        // If $realmId is null, no filtering applied (show all plans)

        if ($this->search) {
            $query->where(function ($q) {
                // Search through celebrations relationship
                $q->whereHas('celebrations', function ($celebrationQuery) {
                    $celebrationQuery->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('season_text', 'ilike', "%{$this->search}%")
                        ->orWhere('year_letter', 'ilike', "%{$this->search}%");
                });
            });
        }

        return $query;
    }

    public function render()
    {
        return view('pages.my-music-plans', [
            'musicPlans' => $this->getMusicPlansQuery()->paginate(12),
        ]);
    }
}
