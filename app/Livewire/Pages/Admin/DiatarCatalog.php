<?php

namespace App\Livewire\Pages\Admin;

use App\Enums\DiatarSyncStatus;
use App\Jobs\SyncDiatarCatalogJob;
use App\Models\DiatarBook;
use App\Models\DiatarSyncRun;
use Illuminate\View\View;
use Livewire\Component;

class DiatarCatalog extends Component
{
    public function sync(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        SyncDiatarCatalogJob::dispatch();
        $this->dispatch('toast', message: __('Diatár catalogue synchronization queued.'), type: 'success');
    }

    public function render(): View
    {
        $latestRun = DiatarSyncRun::query()->latest('started_at')->first();
        $lastSuccessfulRun = DiatarSyncRun::query()
            ->whereIn('status', [DiatarSyncStatus::Completed, DiatarSyncStatus::CompletedWithWarnings])
            ->latest('completed_at')
            ->latest('id')
            ->first();

        return view('livewire.pages.admin.diatar-catalog', [
            'latestRun' => $latestRun,
            'lastSuccessfulRun' => $lastSuccessfulRun,
            'bookCount' => DiatarBook::query()->count(),
            'unavailableCount' => DiatarBook::query()->where('available', false)->count(),
            'staleCount' => $lastSuccessfulRun === null
                ? 0
                : DiatarBook::query()
                    ->where(fn ($query) => $query
                        ->whereNull('last_seen_sync_run_id')
                        ->orWhere('last_seen_sync_run_id', '!=', $lastSuccessfulRun->id))
                    ->count(),
        ]);
    }
}
