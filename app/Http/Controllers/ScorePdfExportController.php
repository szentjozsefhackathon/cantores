<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportScorePdfRequest;
use App\Models\Score;
use App\Services\ScoreAttributionBuilder;
use App\Services\SvgToPdfConverter;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;

class ScorePdfExportController extends Controller
{
    public function __invoke(ExportScorePdfRequest $request, SvgToPdfConverter $converter): Response
    {
        /** @var list<string> $pages */
        $pages = $request->validated('pages');

        try {
            $pdf = $converter->convert($pages, $this->creditFor($request->validated('score_id')));
        } catch (RuntimeException $e) {
            report($e);

            abort(502, __('Could not generate the PDF.'));
        }

        $filename = (Str::slug((string) $request->validated('title')) ?: 'score').'.cantores.hu.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * The credit line to stamp, when the export belongs to a published score.
     *
     * Only published scores are stamped: a private export is the user's own
     * working copy, and a credit line would be noise on it.
     */
    private function creditFor(mixed $scoreId): ?string
    {
        if (! is_numeric($scoreId)) {
            return null;
        }

        $score = Score::query()->with('publication')->find((int) $scoreId);

        if (! $score instanceof Score || ! $score->isPublished()) {
            return null;
        }

        return app(ScoreAttributionBuilder::class)->line($score->publication)
            .' · '.route('public-scores.show', [
                'score' => $score,
                'slug' => Str::slug($score->title) ?: 'kotta',
            ]);
    }
}
