<?php

namespace App\Services;

use App\Contracts\PlanAddedMusic;
use App\Contracts\PlanEntry;
use App\Models\Loan;
use App\Models\Music;
use App\Models\MusicPlanSlotAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What every document made from a music plan has to work out before it can be
 * drawn, whoever is drawing it.
 *
 * A booklet and a projection are one service seen from either side — the handout
 * in the singers' hands, the screen in front of the congregation — and the
 * questions they ask of the plan are the same three: which scores this reader may
 * actually see, which of a score's uploaded files this row means, and what is
 * named above each item. None of the three has anything to do with paper or with
 * projectors, so none of them belongs in either document's own payload.
 *
 * The entitlement is the parameter throughout. A document is built by its owner
 * and read by whoever was sent the link, and both go through
 * MusicPlanScoreListService, resolved afresh on every render, so a recalled loan
 * empties the pages at the same moment it empties everything else.
 */
abstract class PlanRenderPayload
{
    public function __construct(protected readonly MusicPlanScoreListService $scores) {}

    /**
     * The typed source of every score in the document, resolved once.
     *
     * @param  Collection<int, PlanEntry>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    public function sourcesFor(Collection $entries, ?User $viewer, ?Loan $loan = null): Collection
    {
        return $this->scores->sourcesFor(
            $entries->whereNotNull('score_id')->pluck('score_id')->unique()->values()->all(),
            $viewer,
            $loan,
        );
    }

    /**
     * What is printed above each entry, resolved from the plan rather than stored.
     *
     * A document names the moment in the service and the music sung at it. Each is
     * announced once, by whichever row opens it: a slot sung from three engravings
     * is not named three times, and neither is the music. Rows that belong to no
     * slot — a paragraph opening the document, a score chosen outside the plan —
     * say nothing about it and start no naming over.
     *
     * A paragraph opens a slot or a music exactly as a score does, because it was
     * written to introduce that moment, and a heading printed after the words
     * introducing it reads backwards.
     *
     * The music's name joins the slot on its line where the slot holds a single
     * music and there is nothing to tell apart; where the slot holds several,
     * every one of them takes a line of its own beneath the slot's, so they read
     * as the list they are rather than the first being promoted into the heading.
     * A row can be told to keep its music's name off the page, which is how a
     * music the slot already names is stopped from saying it twice.
     *
     * A music only this document holds names itself the way a plan's music does:
     * under the slot it was added in, or — added between slots — its own title
     * takes the slot's line, like a score chosen outside the plan.
     *
     * @param  Collection<int, PlanEntry>  $entries
     * @return array<int, array{slot: ?string, music: ?string, reference: ?string, variation: ?string}>
     */
    public function headingsFor(Collection $entries, ?User $viewer): array
    {
        $assignments = $entries->pluck('assignment')->filter()->keyBy('id');
        $musicCounts = $this->musicCountsPerSlot($entries, $assignments);

        $lines = [];
        $lastSlotKey = null;
        $lastMusicKey = null;

        foreach ($entries as $entry) {
            $added = $entry->added_music_id === null ? null : $entry->addedMusic;

            if ($added instanceof PlanAddedMusic) {
                $lines[$entry->id] = $this->addedMusicHeading($entry, $added, $lastSlotKey, $lastMusicKey, $musicCounts, $viewer);

                $lastSlotKey = $added->music_plan_slot_plan_id ?? $lastSlotKey;
                $lastMusicKey = 'added:'.$added->id;

                continue;
            }

            $assignment = $assignments->get($entry->music_plan_slot_assignment_id);
            $slotKey = $entry->isText()
                ? $entry->music_plan_slot_plan_id
                : $assignment?->music_plan_slot_plan_id;
            $musicKey = $assignment === null ? null : 'music:'.$assignment->id;

            $slotLine = null;

            if (! $entry->show_slot) {
                // The row's own switch: the slot's name is printed unless it is
                // told otherwise, the same way the music's name now is.
                $slotLine = null;
            } elseif (! $entry->isText() && $assignment === null) {
                // Chosen outside the plan, or from an assignment since removed:
                // the score speaks for itself.
                $slotLine = $entry->score?->title;
            } elseif ($slotKey !== null && $slotKey !== $lastSlotKey) {
                $slotLine = $assignment?->musicPlanSlot?->name ?? $entry->slotPlan?->musicPlanSlot?->name;
            }

            // The music is named once, by the row that opens it — and where the
            // score was chosen from no plan at all, by every row of it, there
            // being nothing that groups them.
            $music = $assignment?->music ?? ($entry->isText() ? null : $entry->score?->music);
            $namesMusic = $assignment instanceof MusicPlanSlotAssignment
                ? $musicKey !== $lastMusicKey
                : $music instanceof Music;

            $musicTitle = $namesMusic && $musicKey !== null && $entry->show_music_title
                ? $assignment?->music?->title
                : null;

            // Where it can be looked up follows the name it belongs to, and is
            // asked for on its own: a booklet is printed because the books it
            // would point at are not in every hand, so it says nothing about
            // them until someone wants it to.
            $reference = $namesMusic && $entry->show_collections
                ? $music?->collectionReference($viewer)
                : null;

            $alone = $slotKey !== null && ($musicCounts[$slotKey] ?? 0) <= 1;

            if ($slotLine !== null && $musicTitle !== null && $alone) {
                $slotLine = implode(' – ', [$slotLine, $musicTitle]);
                $musicTitle = null;
            }

            $lines[$entry->id] = [
                'slot' => $slotLine,
                'music' => $musicTitle,
                'reference' => $reference,
                'variation' => ! $entry->isText() && $entry->show_variation
                    ? $entry->score?->variationLabel()
                    : null,
            ];

            $lastSlotKey = $slotKey ?? $lastSlotKey;
            $lastMusicKey = $musicKey ?? $lastMusicKey;
        }

        return $lines;
    }

    /**
     * The heading of a row chosen from a music only this document holds.
     *
     * @param  array<int, int>  $musicCounts
     * @return array{slot: ?string, music: ?string, reference: ?string, variation: ?string}
     */
    private function addedMusicHeading(PlanEntry $entry, PlanAddedMusic $added, ?int $lastSlotKey, ?string $lastMusicKey, array $musicCounts, ?User $viewer): array
    {
        $slotKey = $added->music_plan_slot_plan_id;
        $namesMusic = 'added:'.$added->id !== $lastMusicKey;
        $title = $added->music?->title;

        $slotLine = null;
        $musicTitle = null;

        if ($slotKey === null) {
            // Between slots: the music is the heading, as a score outside the
            // plan is, and it answers to the slot's switch.
            $slotLine = $namesMusic && $entry->show_slot ? $title : null;
        } else {
            $slotLine = $entry->show_slot && $slotKey !== $lastSlotKey
                ? $added->slotPlan?->musicPlanSlot?->name
                : null;
            $musicTitle = $namesMusic && $entry->show_music_title ? $title : null;

            if ($slotLine !== null && $musicTitle !== null && ($musicCounts[$slotKey] ?? 0) <= 1) {
                $slotLine = implode(' – ', [$slotLine, $musicTitle]);
                $musicTitle = null;
            }
        }

        return [
            'slot' => $slotLine,
            'music' => $musicTitle,
            'reference' => $namesMusic && $entry->show_collections
                ? $added->music?->collectionReference($viewer)
                : null,
            'variation' => ! $entry->isText() && $entry->show_variation
                ? $entry->score?->variationLabel()
                : null,
        ];
    }

    /**
     * Which of the score's files this row shows.
     *
     * A row names one where its owner chose between them. Where it does not — an
     * older document, or a score holding a single file — it shows the score's
     * default, and it falls back to that default if the file it named has since
     * been deleted or superseded, since a document that quietly loses a piece is
     * worse than one that shows the score's own first answer.
     *
     * @param  array<string, mixed>  $source
     * @return array{file_id: int|null, strips: list<array<string, mixed>>}
     */
    public function fileOf(PlanEntry $entry, array $source): array
    {
        $chosen = $entry->score_file_id === null ? null : ($source['files'][$entry->score_file_id] ?? null);

        return [
            'file_id' => $chosen['file_id'] ?? $source['file_id'],
            'strips' => $chosen['strips'] ?? $source['strips'],
        ];
    }

    /**
     * How many musics each slot holds in this document, which decides whether a
     * slot heading may carry the music's name itself.
     *
     * Counted from what was chosen rather than from the plan: a slot the plan
     * fills with three musics but the document takes one of is, on the page, a
     * slot with one music, and reads better named on one line.
     *
     * @param  Collection<int, PlanEntry>  $entries
     * @param  Collection<int, MusicPlanSlotAssignment>  $assignments
     * @return array<int, int>
     */
    private function musicCountsPerSlot(Collection $entries, Collection $assignments): array
    {
        return $entries
            ->map(function (PlanEntry $entry) use ($assignments): ?array {
                $added = $entry->added_music_id === null ? null : $entry->addedMusic;

                if ($added instanceof PlanAddedMusic) {
                    return $added->music_plan_slot_plan_id === null
                        ? null
                        : ['slot' => $added->music_plan_slot_plan_id, 'music' => 'added:'.$added->id];
                }

                $assignment = $assignments->get($entry->music_plan_slot_assignment_id);

                return $assignment instanceof MusicPlanSlotAssignment
                    ? ['slot' => $assignment->music_plan_slot_plan_id, 'music' => 'music:'.$assignment->music_id]
                    : null;
            })
            ->filter()
            ->groupBy('slot')
            ->map(fn (Collection $group): int => $group->pluck('music')->unique()->count())
            ->all();
    }
}
