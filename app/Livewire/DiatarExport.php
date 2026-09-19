<?php

namespace App\Livewire;

use App\Models\MusicPlan;
use App\Services\Diatar\DiatarPlanSuggestionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DiatarExport extends Component
{
    use AuthorizesRequests;

    public MusicPlan $musicPlan;

    public bool $show = false;

    public string $catalogRevision = '';

    public ?int $catalogSyncRunId = null;

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    public function mount(MusicPlan $musicPlan, DiatarPlanSuggestionService $suggestions): void
    {
        $this->musicPlan = $musicPlan;

        if (old('rows') !== null) {
            $this->buildSuggestions($suggestions);
            $this->restoreOldSelection(old('rows', []));
            $this->show = true;
        }
    }

    public function open(DiatarPlanSuggestionService $suggestions): void
    {
        $this->authorize('update', $this->musicPlan);

        if ($this->musicPlan->is_private) {
            $this->addError('plan', __('Publish the music plan before exporting it to Diatár.'));

            return;
        }

        $this->resetErrorBag();
        $this->buildSuggestions($suggestions);
        $this->show = true;
    }

    public function selectCandidate(int $rowIndex, int $songId): void
    {
        $candidate = collect($this->rows[$rowIndex]['candidates'] ?? [])->firstWhere('song_id', $songId);
        if ($candidate === null) {
            return;
        }

        $this->rows[$rowIndex]['selected_song_id'] = $songId;
        $this->rows[$rowIndex]['selected_slide_ids'] = collect($candidate['slides'])->pluck('id')->all();
        $this->rows[$rowIndex]['omitted'] = false;
    }

    public function addSlide(int $rowIndex, int $slideId): void
    {
        if ($this->slideForRow($rowIndex, $slideId) === null) {
            return;
        }

        $this->rows[$rowIndex]['selected_slide_ids'][] = $slideId;
    }

    public function removeSlide(int $rowIndex, int $slideIndex): void
    {
        array_splice($this->rows[$rowIndex]['selected_slide_ids'], $slideIndex, 1);
    }

    public function repeatSlide(int $rowIndex, int $slideIndex): void
    {
        $slideId = $this->rows[$rowIndex]['selected_slide_ids'][$slideIndex] ?? null;
        if ($slideId === null) {
            return;
        }

        array_splice($this->rows[$rowIndex]['selected_slide_ids'], $slideIndex + 1, 0, [$slideId]);
    }

    public function moveSlide(int $rowIndex, int $slideIndex, int $direction): void
    {
        $targetIndex = $slideIndex + $direction;
        if (! isset($this->rows[$rowIndex]['selected_slide_ids'][$slideIndex])
            || $targetIndex < 0
            || $targetIndex >= count($this->rows[$rowIndex]['selected_slide_ids'])) {
            return;
        }

        $slides = &$this->rows[$rowIndex]['selected_slide_ids'];
        [$slides[$slideIndex], $slides[$targetIndex]] = [$slides[$targetIndex], $slides[$slideIndex]];
    }

    #[Computed]
    public function canConfirm(): bool
    {
        if ($this->catalogSyncRunId === null || $this->catalogRevision === '' || $this->rows === []) {
            return false;
        }

        return collect($this->rows)->every(fn (array $row): bool => (bool) $row['omitted']
            || ($row['selected_song_id'] !== null && $row['selected_slide_ids'] !== []));
    }

    public function selectedSlide(int $rowIndex, int $slideId): ?array
    {
        return $this->slideForRow($rowIndex, $slideId);
    }

    public function render(): View
    {
        return view('livewire.diatar-export');
    }

    private function buildSuggestions(DiatarPlanSuggestionService $suggestions): void
    {
        $result = $suggestions->forPlan($this->musicPlan);
        $this->catalogSyncRunId = $result['catalog_sync_run_id'];
        $this->catalogRevision = (string) ($result['catalog_revision'] ?? '');
        $this->rows = collect($result['rows'])->map(function (array $row): array {
            $selectedCandidate = collect($row['candidates'])->firstWhere('song_id', $row['selected_song_id']);

            return [
                ...$row,
                'selected_slide_ids' => $selectedCandidate === null
                    ? []
                    : collect($selectedCandidate['slides'])->pluck('id')->all(),
                'omitted' => false,
            ];
        })->all();
    }

    /** @param  array<int, array<string, mixed>>  $oldRows */
    private function restoreOldSelection(array $oldRows): void
    {
        $oldByAssignment = collect($oldRows)->keyBy('assignment_id');

        foreach ($this->rows as $index => $row) {
            $old = $oldByAssignment->get($row['assignment_id']);
            if (! is_array($old)) {
                continue;
            }

            $songId = isset($old['song_id']) && $old['song_id'] !== '' ? (int) $old['song_id'] : null;
            $allowedSongIds = collect($row['candidates'])->pluck('song_id')->map(fn ($id): int => (int) $id);
            if ($songId !== null && $allowedSongIds->contains($songId)) {
                $this->rows[$index]['selected_song_id'] = $songId;
                $this->rows[$index]['selected_slide_ids'] = collect($old['slide_ids'] ?? [])->map(fn ($id): int => (int) $id)->all();
            }
            $this->rows[$index]['omitted'] = (bool) ($old['omitted'] ?? false);
        }
    }

    private function slideForRow(int $rowIndex, int $slideId): ?array
    {
        $row = $this->rows[$rowIndex] ?? null;
        if ($row === null || $row['selected_song_id'] === null) {
            return null;
        }

        $candidate = collect($row['candidates'])->firstWhere('song_id', $row['selected_song_id']);

        return collect($candidate['slides'] ?? [])->firstWhere('id', $slideId);
    }
}
