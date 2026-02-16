<?php

namespace App\Livewire\Pages;

use App\Facades\RealmContext;
use App\Models\MusicPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app.main')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->search = request()->query('search', '');
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

    /**
     * Build the query for fetching published music plans.
     */
    protected function getMusicPlansQuery()
    {
        $query = MusicPlan::query()
            ->where('is_published', true)
            ->orderBy('created_at', 'desc');

        // Filter by current realm if set
        $realmId = RealmContext::getId();
        if ($realmId !== null) {
            // Show plans that belong to the current realm OR have no realm (belongs to all)
            $query->where(function ($q) use ($realmId) {
                $q->whereNull('realm_id')
                    ->orWhere('realm_id', $realmId);
            });
        }

        // Search filter
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

        // Eager load relationships for performance
        $query->with([
            'celebrations',
            'user',
            'realm',
            'musicAssignments.music',
            'musicAssignments.musicPlanSlot',
        ]);

        return $query;
    }

    /**
     * Get the paginated music plans.
     */
    public function getMusicPlansProperty(): LengthAwarePaginator
    {
        return $this->getMusicPlansQuery()->paginate(12);
    }
}
?>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <flux:card class="p-5">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div class="flex items-center gap-4">
                    <flux:icon name="musical-note" class="h-10 w-10 text-blue-600 dark:text-blue-400" variant="outline" />
                    <div>
                        <flux:heading size="xl">Közzétett énekrendek</flux:heading>
                        <flux:text class="text-neutral-600 dark:text-neutral-400">
                            Itt találod az összes nyilvános énekrendet
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <flux:button 
                        href="{{ route('home') }}" 
                        variant="outline" 
                        icon="arrow-left">
                        Vissza
                    </flux:button>
                </div>
            </div>

            <!-- Search and filters -->
            <div class="mb-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <flux:field>
                            <flux:label>Keresés énekrendek között</flux:label>
                            <flux:input 
                                type="search" 
                                wire:model.live="search" 
                                placeholder="Keresés ünnep neve, időszak vagy liturgikus év szerint..." 
                                icon="magnifying-glass" />
                        </flux:field>
                    </div>
                    <div class="flex items-end">
                        <flux:badge color="blue" size="lg" class="px-4 py-2">
                            <flux:icon name="musical-note" class="h-4 w-4 mr-2" variant="mini" />
                            {{ $this->musicPlans->total() }} énekrend
                        </flux:badge>
                    </div>
                </div>
            </div>

            <!-- Music plans grid -->
            @if($this->musicPlans->isEmpty())
                <flux:callout variant="secondary" icon="musical-note" class="border-dashed">
                    <flux:callout.heading>Nincsenek közzétett énekrendek</flux:callout.heading>
                    <flux:callout.text>
                        Jelenleg nincs elérhető nyilvános énekrend. Próbálj meg más keresési feltételeket megadni.
                    </flux:callout.text>
                </flux:callout>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($this->musicPlans as $plan)
                        <livewire:music-plan-card :musicPlan="$plan" :key="$plan->id" readonly="true" />
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($this->musicPlans->hasPages())
                    <div class="mt-8 pt-6 border-t border-neutral-200 dark:border-neutral-800">
                        {{ $this->musicPlans->links() }}
                    </div>
                @endif
            @endif
        </flux:card>
    </div>
</div>