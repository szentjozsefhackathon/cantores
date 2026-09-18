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
    public function __construct(MusicPlanScoreListService $scores, private readonly PlanOutline $outline)
    {
        parent::__construct($scores);
    }

    /**
     * The whole projection, ready to hand to the browser.
     *
     * @return array{geometry: array<string, mixed>, entries: list<array<string, mixed>>, excluded: array<int, list<int>>, outline: list<array<string, mixed>>}
     */
    public function for(Projection $projection, ?User $viewer, ?Loan $loan = null): array
    {
        $entries = $this->entriesOf($projection);
        $sources = $this->sourcesFor($entries, $viewer, $loan);

        return [
            'geometry' => $projection->geometry(),
            'entries' => $this->entries($projection, $entries, $sources, $this->headingsFor($entries, $viewer)),
            'excluded' => $this->exclusions($projection, $entries),
            'outline' => $this->outlineFor($projection, $entries, $viewer),
        ];
    }

    /**
     * The deck read as the plan it came from: every slot the plan gives it and
     * every music in each — not only the ones a score has been chosen from —
     * with what the music could still be sung from riding alongside the ones
     * that have been. This is what lets the remote show a slot nobody has
     * touched yet, and a music sung from none of its engravings, exactly as
     * plainly as one already on the screen.
     *
     * Read off the exact tree the editor's plan pane builds, and only slimmed
     * for the wire: an entry becomes its id, since the full row already travels
     * in `entries`, and a music's `offers` are flattened the way the editor's
     * own row of "+" buttons already reads them. The choosing rule itself lives
     * once, in PlanOutline, so the remote never offers or omits a score the
     * editor would not.
     *
     * A deck with no plan is read the same way: its rows, and whatever music it
     * holds on its own.
     *
     * @param  Collection<int, ProjectionSlide>  $entries
     * @return list<array<string, mixed>>
     */
    public function outlineFor(Projection $projection, Collection $entries, ?User $viewer): array
    {
        $sources = $this->sourcesFor($entries, $viewer);

        // Keyed by row, because what the pane asks is what each music has
        // already taken rather than what the deck holds — see PlanOutline::taken().
        $chosenFiles = $entries
            ->whereNotNull('score_id')
            ->mapWithKeys(function (ProjectionSlide $entry) use ($sources): array {
                $source = $sources->get($entry->score_id);
                $fileId = $source === null ? null : $this->fileOf($entry, $source)['file_id'];

                return $fileId === null ? [] : [$entry->id => $fileId];
            })
            ->all();

        return $this->slimOutline($this->outline->for($projection, $entries, $chosenFiles));
    }

    /**
     * Every node says whether it may move, and says it the way the endpoint
     * will answer: a slot or a music carries PlanOutline's own verdict, and a
     * row — which the editors grey by stylesheet — is worked out here from its
     * siblings, by the same "nearest sibling with anything in it" test. So an
     * arrow the phone shows as live is never a move the server refuses.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function slimOutline(array $nodes): array
    {
        $weights = array_column($nodes, 'weight');

        return array_values(array_map(function (array $node, int $index) use ($weights): array {
            if ($node['kind'] === 'entry') {
                return [
                    'kind' => 'entry',
                    'entryId' => $node['entry']->id,
                    'canMoveUp' => array_sum(array_slice($weights, 0, $index)) > 0,
                    'canMoveDown' => array_sum(array_slice($weights, $index + 1)) > 0,
                ];
            }

            $moves = [
                'canMoveUp' => $node['canMoveUp'] ?? false,
                'canMoveDown' => $node['canMoveDown'] ?? false,
            ];

            if ($node['kind'] === 'music') {
                $local = $node['local'] ?? false;

                return [
                    'kind' => 'music',
                    'local' => $local,
                    'assignmentId' => $local ? null : $node['id'],
                    'addedMusicId' => $local ? $node['id'] : null,
                    'slotId' => $node['slotId'],
                    'title' => $node['title'],
                    'offers' => collect($node['offers'])
                        ->flatMap(fn (array $offer): array => $this->offerLines($offer))
                        ->values()
                        ->all(),
                    'children' => $this->slimOutline($node['children']),
                    ...$moves,
                ];
            }

            return [
                'kind' => 'slot',
                'id' => $node['id'],
                'name' => $node['name'],
                'children' => $this->slimOutline($node['children']),
                ...$moves,
            ];
        }, $nodes, array_keys($nodes)));
    }

    /**
     * One offered score, as one line — or, where it holds several files, one
     * line per file not yet chosen.
     *
     * @param  array{score: array<string, mixed>, files: list<array<string, mixed>>}  $offer
     * @return list<array<string, mixed>>
     */
    private function offerLines(array $offer): array
    {
        $score = $offer['score'];

        if ($offer['files'] === []) {
            return [[
                'scoreId' => $score['id'],
                'fileId' => null,
                'title' => $score['title'],
                'incipitUrl' => $score['incipit_url'] ?? null,
                'inBooklets' => $score['in_booklets'],
            ]];
        }

        return collect($offer['files'])
            ->map(fn (array $file): array => [
                'scoreId' => $score['id'],
                'fileId' => $file['id'],
                'title' => $file['name'],
                'incipitUrl' => $score['incipit_url'] ?? null,
                'inBooklets' => $score['in_booklets'],
            ])
            ->all();
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
            ->with(['score.music.collections', 'scoreFile', 'assignment.music.collections', 'assignment.musicPlanSlot', 'slotPlan.musicPlanSlot', 'addedMusic.music.collections', 'addedMusic.slotPlan.musicPlanSlot'])
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
                        'assignmentId' => $entry->music_plan_slot_assignment_id,
                        'addedMusicId' => $entry->added_music_id,
                        'slot' => $heading['slot'],
                        'music' => $heading['music'],
                        'reference' => $heading['reference'],
                        ...$this->nameOf($entry),
                        // The two numbers a screen of words may be set apart by,
                        // read out of this deck's own shape like every other
                        // override here.
                        'override' => self::overrideOf($entry, 'text', $projection->ratio->value),
                    ];
                }

                $source = $sources->get($entry->score_id);

                if ($source === null) {
                    return null;
                }

                $common = [
                    'id' => $entry->id,
                    'scoreId' => $entry->score_id,
                    'assignmentId' => $entry->music_plan_slot_assignment_id,
                    'addedMusicId' => $entry->added_music_id,
                    'slot' => $heading['slot'],
                    'music' => $heading['music'],
                    'reference' => $heading['reference'],
                    'variation' => $heading['variation'],
                    // The opening notes, for the lists that name the row rather
                    // than draw it: a row of a hymn is recognised by them faster
                    // than by any title, which is why the editor's rows carry
                    // them and why the remote's plan does now too.
                    'incipitUrl' => $source['incipit_url'] ?? null,
                    ...$this->nameOf($entry),
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
                    'sections' => $entry->sections,
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
     * What the row is called when something has to name it.
     *
     * Not the heading: a heading is printed, and is therefore silent wherever
     * the deck's author asked for silence — the slot named once by the row that
     * opens it, the music's title switched off because the room can see it on
     * the slide. A remote's list has the opposite problem. Every row in it has
     * to be recognisable on its own, out of order, at a glance, by the person
     * looking for the Communion hymn while playing the Offertory, so the name
     * is read straight off the music and the score and owes the display
     * switches nothing.
     *
     * What the editor's own row says is said here too, and for the same reason:
     * several arrangements of one music share a title, so the score, the file
     * chosen out of it and the variation are what actually tell two rows of the
     * Communion hymn apart. All of it read straight off the score, owing the
     * display switches nothing.
     *
     * @return array{label: ?string, slotName: ?string, scoreName: ?string, fileName: ?string, variationName: ?string}
     */
    private function nameOf(ProjectionSlide $entry): array
    {
        $slotName = $entry->assignment?->musicPlanSlot?->name
            ?? $entry->slotPlan?->musicPlanSlot?->name;

        $label = $entry->assignment?->music?->title
            ?? $entry->addedMusic?->music?->title
            ?? $entry->score?->music?->title
            ?? $entry->score?->title;

        return [
            'label' => self::trimmed($label),
            'slotName' => self::trimmed($slotName),
            'scoreName' => self::trimmed($entry->score?->title),
            'fileName' => self::trimmed($entry->scoreFile?->displayName()),
            'variationName' => self::trimmed($entry->score?->variation_name),
        ];
    }

    private static function trimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
     * One score the deck has not taken, shaped as a row so it can be drawn.
     *
     * The deck's own twin of BookletRenderPayload::preview, and the same thing
     * in every way that matters: a row to the renderer, nothing at all to the
     * deck. An uploaded score travels as its pages here, as it does for a slide.
     *
     * The entitlement is asked afresh, exactly as it is for a row, so a score
     * the viewer may not read cannot be looked at through the offer either.
     *
     * @return array<string, mixed>|null
     */
    public function preview(Projection $projection, int $scoreId, ?int $fileId, ?User $viewer): ?array
    {
        $source = $this->scores->sourcesFor([$scoreId], $viewer)->get($scoreId);

        if ($source === null) {
            return null;
        }

        $common = [
            'id' => 0,
            'scoreId' => $scoreId,
            'assignmentId' => null,
            'addedMusicId' => null,
            'slot' => null,
            'music' => null,
            'reference' => null,
            'variation' => null,
            'incipitUrl' => $source['incipit_url'] ?? null,
            'override' => [],
        ];

        if ($source['format'] === null) {
            $file = self::fileFrom($source, $fileId);

            return [
                ...$common,
                'kind' => 'file',
                'fileId' => $file['file_id'],
                'pages' => $this->pagesOf($projection, $file),
            ];
        }

        return [
            ...$common,
            'kind' => 'score',
            'format' => $source['format'],
            'content' => $source['content'],
            // Whole: which parts of it a row shows is a decision made on the
            // row, and there is no row yet.
            'sections' => null,
            'settings' => $source['settings'],
        ];
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
