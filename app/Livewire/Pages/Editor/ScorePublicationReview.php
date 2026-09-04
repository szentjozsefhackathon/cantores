<?php

namespace App\Livewire\Pages\Editor;

use App\Enums\ScorePublicationStatus;
use App\Models\ScorePublication;
use App\Models\ScoreRightsReport;
use App\Services\ScorePublicationService;
use App\Services\ScoreRightsReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The queue an editor works through before anything reaches the public library.
 *
 * Structured like Editor\MusicVerifier: a list on the left, the item under
 * consideration on the right, and every decision authorized by a policy rather
 * than by what the template chose to render.
 */
class ScorePublicationReview extends Component
{
    use AuthorizesRequests;

    #[Url]
    public ?int $publicationId = null;

    #[Url]
    public string $status = 'submitted';

    public string $decisionNotes = '';

    public string $takedownReason = '';

    /**
     * What an editor decided about a rights complaint, keyed by report id.
     *
     * @var array<int, string>
     */
    public array $reportNotes = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ScorePublication::class);
    }

    /**
     * @return Collection<int, ScorePublication>
     */
    #[Computed]
    public function queue(): Collection
    {
        return ScorePublication::query()
            ->with(['score.music.authors', 'score.files', 'submitter', 'approvedVersion', 'submittedVersion'])
            // The "submitted" filter is the work queue, not the literal status: a
            // correction queued behind a score that is already published stays
            // Approved so the public keeps reading it, and would otherwise sit in
            // nobody's list.
            ->when(
                $this->status === ScorePublicationStatus::Submitted->value,
                fn ($query) => $query->pending(),
                fn ($query) => $query->when(
                    $this->status !== 'all',
                    fn ($query) => $query->where('status', $this->status)
                )
            )
            ->orderBy('submitted_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function selected(): ?ScorePublication
    {
        if ($this->publicationId === null) {
            return null;
        }

        $publication = ScorePublication::query()
            ->with(['score.music.authors', 'score.files', 'submitter', 'reviewer'])
            ->find($this->publicationId);

        if ($publication === null) {
            return null;
        }

        $this->authorize('view', $publication);

        return $publication;
    }

    /**
     * The files this nomination would publish, with the reviewer's own reason
     * to doubt each one shown beside it.
     *
     * @return list<array{file: \App\Models\ScoreFile, publishable: bool}>
     */
    #[Computed]
    public function reviewableFiles(): array
    {
        $publication = $this->selected;

        if ($publication === null) {
            return [];
        }

        return $publication->score->orderedFiles()
            ->map(fn ($file): array => [
                'file' => $file,
                'publishable' => $file->mayBeOffered(),
            ])
            ->all();
    }

    /**
     * The rights complaints still waiting for a decision on the selected score.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreRightsReport>
     */
    #[Computed]
    public function openReports(): Collection
    {
        $publication = $this->selected;

        if ($publication === null) {
            return new Collection;
        }

        return app(ScoreRightsReportService::class)->openReportsFor($publication);
    }

    /**
     * Open complaint counts for the queue, so a reported score is visible
     * without opening it.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function reportCounts(): array
    {
        return app(ScoreRightsReportService::class)->openReportCounts($this->queue);
    }

    public function select(int $publicationId): void
    {
        $this->publicationId = $publicationId;
        $this->decisionNotes = '';
        $this->takedownReason = '';
        $this->reportNotes = [];
        unset($this->selected, $this->reviewableFiles, $this->openReports);
    }

    public function approve(): void
    {
        $publication = $this->requireSelected();

        // An editor may not clear their own nomination; an admin may, and the
        // service records that they did.
        $selfApproved = ! Auth::user()->can('approve', $publication);

        if ($selfApproved) {
            $this->authorize('selfApprove', $publication);
        } else {
            $this->authorize('approve', $publication);
        }

        app(ScorePublicationService::class)->approve(
            $publication,
            Auth::user(),
            $this->decisionNotes !== '' ? $this->decisionNotes : null,
            $selfApproved,
        );

        $this->afterDecision(__('Published.'));
    }

    public function reject(): void
    {
        $publication = $this->requireSelected();

        // Mirrors approve(): a reviewer may not turn down their own nomination,
        // but an admin may, same as the self-approval escape hatch.
        if (! Auth::user()->can('reject', $publication)) {
            $this->authorize('selfReject', $publication);
        }

        $this->validate([
            'decisionNotes' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'decisionNotes.required' => __('Say why, so the nominator can fix it.'),
        ]);

        app(ScorePublicationService::class)->reject($publication, Auth::user(), $this->decisionNotes);

        $this->afterDecision(__('Rejected.'));
    }

    public function takeDown(): void
    {
        $publication = $this->requireSelected();
        $this->authorize('takeDown', $publication);

        $this->validate([
            'takedownReason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'takedownReason.required' => __('Record why this is being removed.'),
        ]);

        app(ScorePublicationService::class)->takeDown($publication, Auth::user(), $this->takedownReason);

        // A takedown answers every complaint standing against the score, so the
        // queue does not keep asking about a decision already made.
        app(ScoreRightsReportService::class)->upholdOpenReportsFor(
            $publication,
            Auth::user(),
            $this->takedownReason,
        );

        $this->afterDecision(__('Taken down.'));
    }

    public function restore(): void
    {
        $publication = $this->requireSelected();
        $this->authorize('restore', $publication);

        app(ScorePublicationService::class)->restore($publication, Auth::user());

        $this->afterDecision(__('Returned to the queue.'));
    }

    /**
     * Settle a complaint the score survives, on the record.
     */
    public function dismissReport(int $reportId): void
    {
        $publication = $this->requireSelected();
        $this->authorize('handleReports', $publication);

        $report = ScoreRightsReport::query()
            ->where('score_id', $publication->score_id)
            ->findOrFail($reportId);

        $this->validate([
            'reportNotes.'.$reportId => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'reportNotes.'.$reportId.'.required' => __('Record why the complaint was not upheld.'),
        ]);

        app(ScoreRightsReportService::class)->dismiss($report, Auth::user(), $this->reportNotes[$reportId]);

        unset($this->openReports, $this->reportCounts, $this->reportNotes[$reportId]);

        $this->dispatch('toast', message: __('Complaint dismissed.'), type: 'success');
    }

    private function requireSelected(): ScorePublication
    {
        $publication = $this->selected;

        abort_if($publication === null, 404);

        return $publication;
    }

    private function afterDecision(string $message): void
    {
        $this->decisionNotes = '';
        $this->takedownReason = '';
        $this->reportNotes = [];
        unset($this->selected, $this->queue, $this->reviewableFiles, $this->openReports, $this->reportCounts);

        $this->dispatch('toast', message: $message, type: 'success');
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app', ['title' => __('Score publication review')]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.editor.score-publication-review', [
            'statuses' => ScorePublicationStatus::cases(),
        ]);
    }
}
