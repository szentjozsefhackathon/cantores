<?php

namespace App\Livewire\Pages\Admin;

use App\Models\DirektoriumEdition;
use App\Models\DirektoriumEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DirektoriumEntries extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'date')]
    public string $entryDate = '';

    #[Url(as: 'editionFilter')]
    public string $editionFilter = '';

    public function mount(): void
    {
        $this->authorize('system.maintain');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEntryDate(): void
    {
        $this->resetPage();
    }

    public function updatedEditionFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'entryDate', 'editionFilter']);
        $this->resetPage();
    }

    public function render(): View
    {
        $entries = DirektoriumEntry::query()
            ->with('edition')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $searchQuery
                        ->where('markdown_text', 'ilike', '%'.$this->search.'%')
                        ->orWhereHas('edition', function ($editionQuery): void {
                            $editionQuery
                                ->where('original_filename', 'ilike', '%'.$this->search.'%')
                                ->orWhere('year', (int) $this->search);
                        });
                });
            })
            ->when($this->entryDate !== '', fn ($query) => $query->whereDate('entry_date', $this->entryDate))
            ->when($this->editionFilter !== '', fn ($query) => $query->where('direktorium_edition_id', $this->editionFilter))
            ->orderByDesc('entry_date')
            ->orderBy('pdf_page_start')
            ->paginate(25);

        return view('pages.admin.direktorium-entries', [
            'editions' => DirektoriumEdition::query()
                ->orderByDesc('is_current')
                ->orderByDesc('year')
                ->get(['id', 'year', 'is_current']),
            'entries' => $entries,
        ]);
    }
}
