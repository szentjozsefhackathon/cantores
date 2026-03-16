<?php

namespace App\Livewire\Pages\Admin;

use App\Enums\DirektoriumProcessingStatus;
use App\Jobs\ProcessDirektoriumJob;
use App\Models\DirektoriumEdition;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class DirektoriumEditions extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Validate(['required', 'file', 'mimes:md,txt', 'max:20480'])]
    public $pdfFile = null;

    #[Validate(['nullable', 'url', 'max:500'])]
    public string $sourceUrl = '';

    public int $uploadYear;

    public function mount(): void
    {
        $this->authorize('system.maintain');
        $this->uploadYear = now()->year;
    }

    public function uploadPdf(): void
    {
        $this->validate();

        $filename = $this->pdfFile->getClientOriginalName();
        $path = $this->pdfFile->storeAs(
            "direktorium/{$this->uploadYear}",
            $filename,
            'private'
        );

        DirektoriumEdition::create([
            'year' => $this->uploadYear,
            'original_filename' => $filename,
            'source_url' => $this->sourceUrl ?: null,
            'file_path' => $path,
            'processing_status' => DirektoriumProcessingStatus::Pending,
        ]);

        $this->pdfFile = null;
        $this->sourceUrl = '';
        $this->js('window.location.reload()');
    }

    public function process(int $editionId, int $startPage = 1, ?int $endPage = null): void
    {
        $this->authorize('system.maintain');

        $edition = DirektoriumEdition::findOrFail($editionId);

        $edition->update([
            'processing_status' => DirektoriumProcessingStatus::Pending,
            'processed_pages' => 0,
            'processing_error' => null,
            'processing_started_at' => null,
            'processing_completed_at' => null,
        ]);

        ProcessDirektoriumJob::dispatch($edition, $startPage, $endPage);
    }

    public function markAsFailed(int $editionId): void
    {
        $this->authorize('system.maintain');

        $edition = DirektoriumEdition::findOrFail($editionId);

        if ($edition->processing_status !== DirektoriumProcessingStatus::Processing) {
            return;
        }

        $edition->update([
            'processing_status' => DirektoriumProcessingStatus::Failed,
            'processing_error' => 'A feldolgozást kézzel leállítottad, mert a job beragadt.',
            'processing_completed_at' => now(),
        ]);
    }

    public function updateSourceUrl(int $editionId, string $url): void
    {
        $this->authorize('system.maintain');

        validator(['url' => $url], ['url' => ['nullable', 'url', 'max:500']])->validate();

        DirektoriumEdition::findOrFail($editionId)->update(['source_url' => $url ?: null]);
    }

    public function markAsCurrent(int $editionId): void
    {
        $this->authorize('system.maintain');

        DirektoriumEdition::findOrFail($editionId)->markAsCurrent();
    }

    public function delete(int $editionId): void
    {
        $this->authorize('system.maintain');

        $edition = DirektoriumEdition::findOrFail($editionId);

        Storage::disk('private')->delete($edition->file_path);
        $edition->entries()->delete();
        $edition->delete();
    }

    public function render(): View
    {
        return view('pages.admin.direktorium-editions', [
            'editions' => DirektoriumEdition::query()
                ->withCount('entries')
                ->orderByDesc('year')
                ->get(),
        ]);
    }
}
