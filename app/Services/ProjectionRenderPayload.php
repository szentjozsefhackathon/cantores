<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Projection;
use App\Models\ProjectionSlide;
use App\Models\ScoreFile;
use App\Models\User;
use App\Support\ProjectionSettingFields;
use Illuminate\Support\Collection;

/**
 * What the browser needs to draw a projection.
 *
 * The sibling of BookletRenderPayload, and shorter than it for one reason worth
 * stating: a booklet has to describe a page, and a projection does not. A slide
 * is the shape of the screen and nothing else, so this payload carries the
 * score's own source, the score's own layout for this ratio, and whatever one
 * person adjusted by hand — and the browser does the rest.
 *
 * It also stops one step earlier than a booklet's does. A booklet arrives as
 * pages, already flowed; a projection arrives as *scores*, and the cutting into
 * slides happens in the browser, from the source, against this deck's ratio. That
 * is not laziness but the whole point of the feature: the author wrote
 * `%pagebreak169` into the score, and where a slide ends has to be re-read from
 * the score every time it is drawn, or a break moved on Thursday would not be a
 * different screen on Sunday.
 *
 * The entitlement is the parameter, as everywhere: resolved afresh on every
 * render through MusicPlanScoreListService, so a recalled loan empties the deck
 * at the same moment it empties everything else.
 */
class ProjectionRenderPayload extends PlanRenderPayload
{
    /**
     * The whole projection, ready to hand to the browser.
     *
     * @return array{geometry: array<string, mixed>, entries: list<array<string, mixed>>, excluded: array<int, list<int>>}
     */
    public function for(Projection $projection, ?User $viewer, ?Loan $loan = null): array
    {
        $entries = $this->entriesOf($projection);
        $sources = $this->sourcesFor($entries, $viewer, $loan);

        return [
            'geometry' => $projection->geometry(),
            'entries' => $this->entries($projection, $entries, $sources, $this->headingsFor($entries, $viewer)),
            'excluded' => $this->exclusions($projection, $entries),
        ];
    }

    /**
     * Which slides the service walks past, row by row, at this deck's shape.
     *
     * Handed over beside the rows rather than inside them, and that is a
     * deliberate line: what is drawn and what is shown are different questions.
     * The browser re-engraves a deck whenever the thing to draw has changed, and
     * skipping a verse changes nothing about the drawing — every slide is still
     * cut, still engraved, still in the editor's contact sheet where it can be
     * put back. Folded into a row, it would cost a full re-engraving of the deck
     * per click.
     *
     * @param  Collection<int, ProjectionSlide>  $entries
     * @return array<int, list<int>>
     */
    public function exclusions(Projection $projection, Collection $entries): array
    {
        $ratio = $projection->ratio->value;

        return $entries
            ->mapWithKeys(fn (ProjectionSlide $entry): array => [$entry->id => $entry->excludedFor($ratio)])
            ->filter(fn (array $excluded): bool => $excluded !== [])
            ->all();
    }

    /**
     * The rows, in order, with everything a heading or a source is read from.
     *
     * @return Collection<int, ProjectionSlide>
     */
    public function entriesOf(Projection $projection): Collection
    {
        return $projection->entries()
            ->with(['score.music.collections', 'scoreFile', 'assignment.music.collections', 'assignment.musicPlanSlot', 'slotPlan.musicPlanSlot'])
            ->get();
    }

    /**
     * What the browser draws, row by row.
     *
     * @param  Collection<int, ProjectionSlide>  $entries
     * @param  Collection<int, array<string, mixed>>  $sources
     * @param  array<int, array{slot: ?string, music: ?string, reference: ?string, variation: ?string}>  $headings
     * @return list<array<string, mixed>>
     */
    public function entries(Projection $projection, Collection $entries, Collection $sources, array $headings): array
    {
        return $entries
            ->map(function (ProjectionSlide $entry) use ($projection, $sources, $headings): ?array {
                $heading = $headings[$entry->id] ?? ['slot' => null, 'music' => null, 'reference' => null, 'variation' => null];

                if ($entry->isText()) {
                    return [
                        'id' => $entry->id,
                        'kind' => 'text',
                        'text' => $entry->text ?? '',
                        'slot' => $heading['slot'],
                        'music' => $heading['music'],
                        'reference' => $heading['reference'],
                    ];
                }

                $source = $sources->get($entry->score_id);

                if ($source === null) {
                    return null;
                }

                $common = [
                    'id' => $entry->id,
                    'scoreId' => $entry->score_id,
                    'slot' => $heading['slot'],
                    'music' => $heading['music'],
                    'reference' => $heading['reference'],
                    'variation' => $heading['variation'],
                ];

                // An uploaded score has no source to re-engrave, so it travels
                // as its pages rather than as its systems: a projection shows a
                // scan one whole page at a time, letterboxed into the screen,
                // because the alternative — cutting it into staves and packing
                // them — is a decision about pacing that only the person at the
                // keyboard during the service can make.
                if ($source['format'] === null) {
                    $file = $this->fileOf($entry, $source);

                    return [
                        ...$common,
                        'kind' => 'file',
                        'fileId' => $file['file_id'],
                        'override' => self::overrideOf($entry, 'file', $projection->ratio->value),
                        'pages' => $this->pagesOf($projection, $file),
                    ];
                }

                return [
                    ...$common,
                    'kind' => 'score',
                    'format' => $source['format'],
                    'content' => $source['content'],
                    // The score's whole settings column, not this ratio's slice
                    // of it: which slice is read is the deck's ratio, and the
                    // browser already knows that from the geometry.
                    'settings' => $source['settings'],
                    'override' => self::overrideOf($entry, $source['format'], $projection->ratio->value),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The pages of an uploaded score, each its own slide.
     *
     * Read off the strips rather than stored separately — a strip names the page
     * it was cut from, so the pages of a file are the distinct page numbers its
     * systems mention, in order. That keeps a projection honest about which
     * pages actually engraved: a page that produced no systems produced nothing
     * worth putting on a screen either.
     *
     * @param  array{file_id: int|null, strips: list<array<string, mixed>>}  $file
     * @return list<array<string, mixed>>
     */
    private function pagesOf(Projection $projection, array $file): array
    {
        if ($file['file_id'] === null) {
            return [];
        }

        $pages = collect($file['strips'])
            ->pluck('page')
            ->filter(fn ($page): bool => $page !== null)
            ->map(fn ($page): int => (int) $page)
            ->unique()
            ->sort()
            ->values();

        return $pages
            ->map(fn (int $page): array => [
                'page' => $page,
                'url' => $this->pageUrl($projection, (int) $file['file_id'], $page),
            ])
            ->all();
    }

    /**
     * Whether a shared projection really draws this file.
     *
     * The same two-part question BookletRenderPayload::drawsFile asks, and for
     * the same reasons: the deck must actually show the score, because that is
     * the whole of what a link promises, and the reader must be entitled to the
     * score, which is MusicPlanScoreListService's answer and nobody else's,
     * asked afresh so a recalled loan underneath this one closes these URLs with
     * it.
     */
    public function drawsFile(Projection $projection, ScoreFile $scoreFile, ?User $viewer, ?Loan $loan = null): bool
    {
        $shows = $projection->entries()
            ->where('score_id', $scoreFile->score_id)
            ->exists();

        if (! $shows) {
            return false;
        }

        return $this->scores->sourcesFor([$scoreFile->score_id], $viewer, $loan)->isNotEmpty();
    }

    /**
     * What this projection has been told to do differently to one score — as the
     * server would keep it, not as it happens to sit in the column.
     *
     * Sanitised on the way out as well as on the way in, for the reason the
     * booklet's equivalent explains: a stored override outlives the table it was
     * written against, and a browser handed the column raw would draw a deck the
     * next save contradicts, then have to lay it all out again to agree.
     *
     * Only this deck's shape travels. The column keeps a bucket per shape — a
     * size chosen for a widescreen says nothing about a square screen — and the
     * browser is drawing one of them, so it is handed that one flat, in exactly
     * the vocabulary the score's own layout for this ratio is written in.
     *
     * @return array<string, mixed>
     */
    private static function overrideOf(ProjectionSlide $entry, ?string $format, string $ratio): array
    {
        return ProjectionSettingFields::sanitize($format ?? 'file', $entry->overrideFor($ratio));
    }

    /**
     * An uploaded page, addressed through the deck that shows it.
     *
     * One door, because a projection has one: there is no lending link yet. When
     * there is, this grows the branch BookletRenderPayload::pageUrl already has
     * — a route per door, so a token cannot be left out of a URL by accident —
     * and `for()`'s loan, which today only widens what may be read, will be
     * what picks between them.
     */
    private function pageUrl(Projection $projection, int $fileId, int $page): string
    {
        return route('projections.score-page', [
            'projection' => $projection->id,
            'scoreFile' => $fileId,
            'page' => $page,
        ]);
    }
}
