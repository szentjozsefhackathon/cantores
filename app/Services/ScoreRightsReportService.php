<?php

namespace App\Services;

use App\Enums\ScoreRightsReportStatus;
use App\Models\Score;
use App\Models\ScorePublication;
use App\Models\ScoreRightsReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Filing and settling rights complaints made against a published score.
 *
 * A report never changes what the public can see on its own: an editor decides,
 * and the decision is recorded on the report next to the claim it answers.
 */
class ScoreRightsReportService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Record a complaint and put it in front of the people who can act on it.
     *
     * @param  array{capacity: \App\Enums\ScoreRightsClaimantCapacity, claim: string, reporter_name: string, reporter_email: string}  $attributes
     */
    public function file(Score $score, array $attributes, ?User $reporter = null): ScoreRightsReport
    {
        $report = ScoreRightsReport::create([
            'score_id' => $score->getKey(),
            'score_publication_id' => $score->publication?->getKey(),
            'status' => ScoreRightsReportStatus::Open,
            'capacity' => $attributes['capacity'],
            'claim' => $attributes['claim'],
            'reporter_id' => $reporter?->getKey(),
            'reporter_name' => $attributes['reporter_name'],
            'reporter_email' => $attributes['reporter_email'],
        ]);

        $this->notifications->createRightsReport($score, $reporter, $this->summarize($report));

        return $report;
    }

    /**
     * Settle a report without removing the score.
     */
    public function dismiss(ScoreRightsReport $report, User $actor, string $notes): void
    {
        $this->settle($report, ScoreRightsReportStatus::Dismissed, $actor, $notes);
    }

    /**
     * Settle every open report against a publication that has just been taken
     * down, so the queue does not keep asking about a decision already made.
     */
    public function upholdOpenReportsFor(ScorePublication $publication, User $actor, string $notes): void
    {
        $this->openReportsFor($publication)->each(
            fn (ScoreRightsReport $report) => $this->settle($report, ScoreRightsReportStatus::Upheld, $actor, $notes)
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreRightsReport>
     */
    public function openReportsFor(ScorePublication $publication): Collection
    {
        return ScoreRightsReport::query()
            ->open()
            ->where('score_id', $publication->score_id)
            ->with('reporter')
            ->latest()
            ->get();
    }

    /**
     * How many open reports each of the given publications carries, keyed by
     * publication id, so a queue can flag them without an N+1.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\ScorePublication>  $publications
     * @return array<int, int>
     */
    public function openReportCounts(SupportCollection $publications): array
    {
        $scoreIds = $publications->pluck('score_id')->all();

        if ($scoreIds === []) {
            return [];
        }

        $countsByScore = ScoreRightsReport::query()
            ->open()
            ->whereIn('score_id', $scoreIds)
            ->groupBy('score_id')
            ->selectRaw('score_id, count(*) as aggregate')
            ->pluck('aggregate', 'score_id');

        $counts = [];

        foreach ($publications as $publication) {
            $count = (int) ($countsByScore[$publication->score_id] ?? 0);

            if ($count > 0) {
                $counts[$publication->getKey()] = $count;
            }
        }

        return $counts;
    }

    private function settle(ScoreRightsReport $report, ScoreRightsReportStatus $status, User $actor, string $notes): void
    {
        if (! $report->status->isOpen()) {
            return;
        }

        $report->update([
            'status' => $status,
            'handled_by' => $actor->getKey(),
            'handled_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * The complaint as one block of text, for the notification that carries it
     * to the editors' inbox and email.
     */
    private function summarize(ScoreRightsReport $report): string
    {
        return __(':title — reported by :name (:capacity), reachable at :email: :claim', [
            'title' => $report->score->title,
            'name' => $report->reporter_name,
            'capacity' => $report->capacity->label(),
            'email' => $report->reporter_email,
            'claim' => $report->claim,
        ]);
    }
}
