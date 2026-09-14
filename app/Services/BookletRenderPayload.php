<?php

namespace App\Services;

use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\Loan;
use App\Models\ScoreFile;
use App\Models\User;
use App\Support\BookletSettingFields;
use Illuminate\Support\Collection;

/**
 * What the browser needs to draw a booklet, wherever it is being drawn.
 *
 * The half of it that is about paper. Everything a projection asks of the plan
 * too — whose scores these are, which file a row means, what is named above each
 * item — is in PlanRenderPayload above this; what is left here is the page, the
 * systems cut out of an uploaded one, and the URLs they are fetched by.
 *
 * Two people look at one booklet: the cantor who is building it, in the editor,
 * and the musicians who were sent the link, on their phones. They must see the
 * same handout — the same scores, in the same order, under the same headings —
 * or the link is not a copy of the booklet but a second document that drifts.
 * So the payload is built once, here, and the two views differ only in what the
 * caller does with it and in whose entitlement it was resolved under.
 *
 * The entitlement is the parameter. The editor asks as its owner; the shared
 * link asks as the loan it arrived on. Both go through
 * MusicPlanScoreListService, resolved afresh on every render, so a recalled loan
 * empties the pages at the same moment it empties everything else.
 */
class BookletRenderPayload extends PlanRenderPayload
{
    /**
     * The whole booklet, ready to hand to the browser.
     *
     * @return array{geometry: array<string, mixed>, entries: list<array<string, mixed>>}
     */
    public function for(Booklet $booklet, ?User $viewer, ?Loan $loan = null): array
    {
        $entries = $this->entriesOf($booklet);
        $sources = $this->sourcesFor($entries, $viewer, $loan);

        return [
            'geometry' => $booklet->geometry(),
            'entries' => $this->entries($booklet, $entries, $sources, $this->headingsFor($entries, $viewer), $loan),
        ];
    }

    /**
     * The rows, in order, with everything a heading or a source is read from.
     *
     * @return Collection<int, BookletScore>
     */
    public function entriesOf(Booklet $booklet): Collection
    {
        return $booklet->entries()
            ->with(['score.music.collections', 'scoreFile', 'assignment.music.collections', 'assignment.musicPlanSlot', 'slotPlan.musicPlanSlot'])
            ->get();
    }

    /**
     * What the browser draws, entry by entry.
     *
     * The sources come from MusicPlanScoreListService, so a score reaches the
     * page exactly when the reader may read it — and stops reaching it the moment
     * a loan is recalled, since this is resolved afresh on every render.
     *
     * @param  Collection<int, BookletScore>  $entries
     * @param  Collection<int, array<string, mixed>>  $sources
     * @param  array<int, array{slot: ?string, music: ?string, reference: ?string, variation: ?string}>  $headings
     * @return list<array<string, mixed>>
     */
    public function entries(Booklet $booklet, Collection $entries, Collection $sources, array $headings, ?Loan $loan = null): array
    {
        return $entries
            ->map(function (BookletScore $entry) use ($booklet, $sources, $headings, $loan): ?array {
                $heading = $headings[$entry->id] ?? ['slot' => null, 'music' => null, 'reference' => null, 'variation' => null];

                if ($entry->isText()) {
                    return [
                        'id' => $entry->id,
                        'kind' => 'text',
                        'text' => $entry->text ?? '',
                        // A paragraph that opens a slot — or one of its musics —
                        // carries that name, so a rubric written under the
                        // heading is printed under it rather than above it.
                        'slot' => $heading['slot'],
                        'music' => $heading['music'],
                        'reference' => $heading['reference'],
                        // The two numbers a paragraph may be set apart by: how
                        // large it is beside the music, and how far apart its
                        // lines stand. Both are the booklet's until this row
                        // says otherwise.
                        'override' => BookletSettingFields::sanitize('text', $entry->settings_override ?? []),
                        'startOnNewPage' => $entry->start_on_new_page,
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
                    'startOnNewPage' => $entry->start_on_new_page,
                ];

                // An uploaded score has no source to re-engrave, so it travels
                // as the systems it was cut into — by URL rather than inline,
                // because this payload crosses the wire on every change.
                //
                // A vector-rendered file names its page once and each system as
                // a rectangle onto it; a raster one names each system's own
                // image. The browser tells them apart by which URL is present.
                if ($source['format'] === null) {
                    $file = $this->fileOf($entry, $source);

                    return [
                        ...$common,
                        'kind' => 'file',
                        'fileId' => $file['file_id'],
                        'override' => self::overrideOf($entry, 'file'),
                        'strips' => array_map(function (array $strip) use ($booklet, $file, $loan): array {
                            if (isset($strip['rect'])) {
                                return [
                                    'pageUrl' => $this->pageUrl($booklet, $loan, (int) $file['file_id'], (int) $strip['page']),
                                    // Named as well as addressed: the browser
                                    // scopes a page's cairo ids by it, and the
                                    // export placeholder carries it back so the
                                    // server knows which page to inline.
                                    'page' => $strip['page'],
                                    'rect' => implode(' ', $strip['rect']),
                                    'width' => $strip['width'],
                                    'height' => $strip['height'],
                                ];
                            }

                            return [
                                'url' => $this->stripUrl($booklet, $loan, (int) $file['file_id'], (int) $strip['page'], (int) $strip['index']),
                                'width' => $strip['width'],
                                'height' => $strip['height'],
                            ];
                        }, $file['strips']),
                    ];
                }

                return [
                    ...$common,
                    'kind' => 'score',
                    'format' => $source['format'],
                    'content' => $source['content'],
                    'settings' => $source['settings'],
                    'override' => self::overrideOf($entry, $source['format']),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Whether a shared booklet really draws this file.
     *
     * The question the two lending endpoints ask before serving a system or a
     * page, and both halves of it matter. The booklet must actually print the
     * score, because that is the whole of what the link promises — this handout,
     * not everything the cantor owns; and the reader must be entitled to the
     * score, which is MusicPlanScoreListService's answer and nobody else's,
     * asked afresh so that a recalled loan underneath this one closes these URLs
     * with it.
     *
     * The owner's own two endpoints ask only the second half, and rightly:
     * containment is no boundary to someone who can put any score they can read
     * into the booklet with one click.
     */
    public function drawsFile(Booklet $booklet, ScoreFile $scoreFile, ?User $viewer, ?Loan $loan = null): bool
    {
        $prints = $booklet->entries()
            ->where('score_id', $scoreFile->score_id)
            ->exists();

        if (! $prints) {
            return false;
        }

        return $this->scores->sourcesFor([$scoreFile->score_id], $viewer, $loan)->isNotEmpty();
    }

    /**
     * What this booklet has been told to do differently to one score — as the
     * server would keep it, not as it happens to sit in the column.
     *
     * The same sanitising a save goes through, applied on the way out as well.
     * A stored override outlives the table it was written against: a score
     * re-entered in another format leaves its old format's keys behind, and a
     * knob taken off the panel leaves every override that named it. The server
     * drops all of that the moment anything on that row is saved — so a browser
     * handed the column raw draws a booklet the next save will contradict, and
     * then has to lay the whole thing out again to agree with it. That second
     * layout is where the "laying out" badge came from *after* the preview had
     * already settled.
     *
     * Nothing is written here. The stale keys stay in the column until that row
     * is next saved; they simply stop being drawn with.
     *
     * @return array<string, mixed>
     */
    private static function overrideOf(BookletScore $entry, ?string $format): array
    {
        return BookletSettingFields::sanitize($format ?? 'file', $entry->settings_override ?? []);
    }

    /**
     * An uploaded page, addressed through whichever door the reader came in by.
     *
     * The owner's routes are behind the booklet's own policy; the reader's are
     * behind the token they were sent. Same page, same check underneath — see
     * drawsFile() — and no route that answers to both, so a token can never be
     * left out of a URL by accident.
     */
    private function pageUrl(Booklet $booklet, ?Loan $loan, int $fileId, int $page): string
    {
        return $loan instanceof Loan
            ? route('booklet.loan.score-page', ['token' => $loan->token, 'scoreFile' => $fileId, 'page' => $page])
            : route('booklets.score-page', ['booklet' => $booklet->id, 'scoreFile' => $fileId, 'page' => $page]);
    }

    private function stripUrl(Booklet $booklet, ?Loan $loan, int $fileId, int $page, int $index): string
    {
        return $loan instanceof Loan
            ? route('booklet.loan.strip', ['token' => $loan->token, 'scoreFile' => $fileId, 'page' => $page, 'index' => $index])
            : route('booklets.strip', ['booklet' => $booklet->id, 'scoreFile' => $fileId, 'page' => $page, 'index' => $index]);
    }
}
