<?php

namespace App\Livewire\Pages;

use App\Facades\GenreContext;
use App\Models\MusicPlan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    public function rendering(View $view): void
    {
        $layout = Auth::check() ? 'layouts::app' : 'layouts::app.main';

        $view->layout($layout);
    }

    use WithPagination;

    public string $liturgicalSearch = '';

    public string $customSearch = '';

    public string $tab = 'liturgical';

    public function mount(): void {}

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingLiturgicalSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCustomSearch(): void
    {
        $this->resetPage();
    }

    #[On('genre-changed')]
    public function onGenreChanged(): void
    {
        $this->resetPage();
    }

    /**
     * Build a base query for fetching published music plans.
     */
    protected function baseQuery(string $search = '')
    {
        $query = MusicPlan::query()
            ->where('is_private', false)
            ->leftJoin('celebrations', 'celebrations.id', '=', 'music_plans.celebration_id')
            ->orderBy('celebrations.actual_date', 'desc')
            ->orderBy('music_plans.created_at', 'desc')
            ->select('music_plans.*');

        // Filter by current genre if set
        $genreId = GenreContext::getId();
        if ($genreId !== null) {
            $query->where(function ($q) use ($genreId) {
                $q->whereNull('genre_id')
                    ->orWhere('genre_id', $genreId);
            });
        }

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('celebration', function ($celebrationQuery) use ($search) {
                    $celebrationQuery->where('name', 'ilike', "%{$search}%")
                        ->orWhere('season_text', 'ilike', "%{$search}%")
                        ->orWhere('year_letter', 'ilike', "%{$search}%");
                });
            });
        }

        // Eager load relationships for performance
        $query->with([
            'celebration',
            'user',
            'genre',
            'musicAssignments.music',
            'musicAssignments.musicPlanSlot',
        ]);

        return $query;
    }

    /**
     * Get paginated liturgical (non-custom) music plans.
     */
    public function getLiturgicalPlansProperty(): LengthAwarePaginator
    {
        return $this->baseQuery($this->liturgicalSearch)
            ->where(function ($q) {
                $q->whereNull('celebrations.is_custom')
                    ->orWhere('celebrations.is_custom', false);
            })
            ->paginate(12);
    }

    /**
     * Get paginated custom celebration music plans.
     */
    public function getCustomPlansProperty(): LengthAwarePaginator
    {
        return $this->baseQuery($this->customSearch)
            ->where('celebrations.is_custom', true)
            ->paginate(12);
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

            <!-- Tabs -->
            <x-mary-tabs wire:model="tab">
                <x-mary-tab name="liturgical" label="Liturgikus ünnepek" icon="o-calendar-days">
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="flex-1">
                            <flux:field>
                                <flux:label>Keresés énekrendek között</flux:label>
                                <flux:input
                                    type="search"
                                    wire:model.live.debounce.500ms="liturgicalSearch"
                                    placeholder="Keresés ünnep neve vagy időszak szerint..."
                                    icon="magnifying-glass" />
                            </flux:field>
                        </div>
                        <div class="flex items-end">
                            <flux:badge color="blue" size="lg" class="px-4 py-2">
                                <flux:icon name="musical-note" class="h-4 w-4 mr-2" variant="mini" />
                                {{ $this->liturgicalPlans->total() }} énekrend
                            </flux:badge>
                        </div>
                    </div>
                    @if($this->liturgicalPlans->isEmpty())
                    <flux:callout variant="secondary" icon="musical-note" class="border-dashed">
                        <flux:callout.heading>Nincsenek közzétett énekrendek</flux:callout.heading>
                        <flux:callout.text>
                            Jelenleg nincs elérhető nyilvános liturgikus énekrend. Próbálj meg más keresési feltételeket megadni.
                        </flux:callout.text>
                    </flux:callout>
                    @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($this->liturgicalPlans as $plan)
                        <livewire:music-plan-card :musicPlan="$plan" :key="$plan->id" readonly="true" />
                        @endforeach
                    </div>
                    @if($this->liturgicalPlans->hasPages())
                    <div class="mt-8 pt-6 border-t border-neutral-200 dark:border-neutral-800">
                        {{ $this->liturgicalPlans->links() }}
                    </div>
                    @endif
                    @endif
                </x-mary-tab>
                <x-mary-tab name="custom" label="Egyéni ünnepek" icon="o-star">
                    <div class="flex flex-col md:flex-row gap-4 mb-6">
                        <div class="flex-1">
                            <flux:field>
                                <flux:label>Keresés énekrendek között</flux:label>
                                <flux:input
                                    type="search"
                                    wire:model.live.debounce.500ms="customSearch"
                                    placeholder="Keresés ünnep neve vagy időszak szerint..." 
                                    icon="magnifying-glass" />
                            </flux:field>
                        </div>
                        <div class="flex items-end">
                            <flux:badge color="blue" size="lg" class="px-4 py-2">
                                <flux:icon name="musical-note" class="h-4 w-4 mr-2" variant="mini" />
                                {{ $this->customPlans->total() }} énekrend
                            </flux:badge>
                        </div>
                    </div>
                    @if($this->customPlans->isEmpty())
                    <flux:callout variant="secondary" icon="musical-note" class="border-dashed">
                        <flux:callout.heading>Nincsenek közzétett énekrendek</flux:callout.heading>
                        <flux:callout.text>
                            Jelenleg nincs elérhető nyilvános egyéni énekrend. Próbálj meg más keresési feltételeket megadni.
                        </flux:callout.text>
                    </flux:callout>
                    @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($this->customPlans as $plan)
                        <livewire:music-plan-card :musicPlan="$plan" :key="$plan->id" readonly="true" />
                        @endforeach
                    </div>
                    @if($this->customPlans->hasPages())
                    <div class="mt-8 pt-6 border-t border-neutral-200 dark:border-neutral-800">
                        {{ $this->customPlans->links() }}
                    </div>
                    @endif
                    @endif
                </x-mary-tab>
            </x-mary-tabs>
        </flux:card>
    </div>
</div>