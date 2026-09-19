<?php

namespace App\Services\Diatar;

use App\Enums\DiatarSyncStatus;
use App\Models\DiatarSlide;
use App\Models\DiatarSong;
use App\Models\DiatarSyncRun;
use App\Models\MusicPlan;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiatarExportService
{
    public function __construct(
        private DiatarPlanSuggestionService $suggestions,
        private DiatarWriter $writer,
    ) {}

    /**
     * @param  array{catalog_sync_run_id: int, catalog_revision: string, rows: list<array{assignment_id: int, song_id: ?int, omitted: bool, slide_ids: list<int>}>}  $selection
     * @return array{contents: string, filename: string}
     */
    public function export(MusicPlan $musicPlan, array $selection): array
    {
        $currentSyncRun = DiatarSyncRun::query()
            ->whereIn('status', [DiatarSyncStatus::Completed, DiatarSyncStatus::CompletedWithWarnings])
            ->latest('completed_at')
            ->latest('id')
            ->first(['id', 'source_revision']);

        if ($currentSyncRun === null
            || $currentSyncRun->id !== (int) $selection['catalog_sync_run_id']
            || ! hash_equals($currentSyncRun->source_revision, $selection['catalog_revision'])) {
            throw ValidationException::withMessages([
                'catalog_revision' => __('The Diatár catalogue changed. The suggestions have been refreshed; review them and try the download again.'),
            ]);
        }

        $suggestions = $this->suggestions->forPlan($musicPlan);
        $submittedRows = collect($selection['rows'])->keyBy('assignment_id');
        $expectedAssignmentIds = collect($suggestions['rows'])->pluck('assignment_id');

        if ($submittedRows->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all()
            !== $expectedAssignmentIds->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'rows' => __('The music plan changed. Reopen the dialog and review every assignment.'),
            ]);
        }

        $songs = DiatarSong::query()
            ->with('book')
            ->whereKey($submittedRows->pluck('song_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $slides = DiatarSlide::query()
            ->whereKey($submittedRows->pluck('slide_ids')->flatten()->unique())
            ->get()
            ->keyBy('id');
        $outputSlides = [];

        foreach ($suggestions['rows'] as $rowIndex => $suggestion) {
            $submitted = $submittedRows->get($suggestion['assignment_id']);

            if ((bool) $submitted['omitted']) {
                continue;
            }

            if ($submitted['slide_ids'] === []) {
                throw ValidationException::withMessages([
                    "rows.{$rowIndex}.slide_ids" => __('Select at least one slide or explicitly omit this music.'),
                ]);
            }

            $songId = (int) ($submitted['song_id'] ?? 0);
            $allowedSongIds = collect($suggestion['candidates'])->pluck('song_id')->map(fn ($id): int => (int) $id);
            $song = $songs->get($songId);

            if (! $allowedSongIds->contains($songId) || ! $song instanceof DiatarSong || ! $song->available || ! $song->book->available) {
                throw ValidationException::withMessages([
                    "rows.{$rowIndex}.song_id" => __('The selected Diatár source is no longer available for this music.'),
                ]);
            }

            foreach ($submitted['slide_ids'] as $slideId) {
                $slide = $slides->get((int) $slideId);

                if (! $slide instanceof DiatarSlide
                    || $slide->diatar_song_id !== $song->id
                    || ! $slide->is_exportable
                    || $slide->external_id === null) {
                    throw ValidationException::withMessages([
                        "rows.{$rowIndex}.slide_ids" => __('One of the selected slides is stale, ambiguous, or unavailable.'),
                    ]);
                }

                $outputSlides[] = [
                    'external_id' => $slide->external_id,
                    'book_title' => $song->book->title,
                    'song_title' => $song->title,
                    'verse_name' => $slide->verse_name,
                ];
            }
        }

        return [
            'contents' => $this->writer->write($outputSlides),
            'filename' => $this->filename($musicPlan),
        ];
    }

    private function filename(MusicPlan $musicPlan): string
    {
        $title = $musicPlan->celebration_name ?: __('music-plan');
        if ($musicPlan->actual_date !== null) {
            $title .= '-'.$musicPlan->actual_date->format('Y-m-d');
        }

        $slug = Str::slug($title);

        return Str::limit($slug !== '' ? $slug : 'enekrend', 120, '').'.dia';
    }
}
