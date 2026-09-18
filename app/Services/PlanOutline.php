<?php

namespace App\Services;

use App\Contracts\PlanAddedMusic;
use App\Contracts\PlanDocument;
use App\Contracts\PlanEntry;
use App\Models\MusicPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A document seen as the plan it was made from.
 *
 * A booklet is a flat list of rows in one order — that is what is printed, and
 * that is all the renderer is ever told. But it is chosen from a service, and a
 * service has a shape: slots, the music sung in each of them, the engravings that
 * music can be sung from. Reading a flat list against a plan to find out what
 * made it in is the one thing nobody could do, so the plan is what is shown and
 * this is what puts the two together.
 *
 * The tree is derived on every render and stored nowhere. A slot stands where its
 * first row stands, so a slot can be pulled out of liturgical order for the
 * document without the plan hearing about it; a slot holding nothing keeps the
 * place the plan gives it, since it has nothing to be moved. Everything the
 * document holds comes before everything it merely could hold, so what is read
 * downwards is the document, and the rest of the plan waits underneath it.
 *
 * The order the tree is walked in *is* the order it is read: flatten() is what
 * the document's sequences are then written from, which is what keeps the two
 * from ever drifting apart.
 *
 * A projection is chosen in exactly the same way as a booklet — the two are one
 * service seen from either side of it, the handout in the singers' hands and the
 * screen in front of the congregation — so this answers for both, through
 * App\Contracts\PlanDocument. It knows nothing about paper or about screens:
 * what a document does with the order it is given is the document's own business.
 *
 * A document may also hold a music its plan does not (App\Contracts\PlanAddedMusic).
 * It is a music node like any other, marked `local`, standing inside the slot it
 * was added in or between slots when it has none. Every node that is not a row
 * carries a `key` — `slot:4`, `music:12`, `added:5` — which is what the tree is
 * searched by, so a plan's music and a document's own music never share an id.
 */
class PlanOutline
{
    public function __construct(private MusicPlanScoreListService $scores) {}

    /**
     * The whole left-hand pane: slots, the music in them, and what of it the
     * booklet took.
     *
     * The entries are handed in rather than read again — the editor already holds
     * them, and reads them once for everything it draws.
     *
     * `$chosenFiles` says which uploaded file each row actually draws, keyed by
     * row: a row that names no file draws the score's default one, and resolving
     * that is the document's business rather than this one's. The scores are read
     * straight off the rows, since a row names its own.
     *
     * @param  Collection<int, PlanEntry>  $entries
     * @param  array<int, int>  $chosenFiles  file id, keyed by entry id
     * @return list<array<string, mixed>>
     */
    public function for(PlanDocument $document, Collection $entries, array $chosenFiles = []): array
    {
        $plan = $document->musicPlan;
        $viewer = Auth::user();
        $slots = $plan instanceof MusicPlan ? $this->planSlots($plan) : [];
        $slotIds = array_column($slots, 'id');

        /** @var Collection<int, PlanAddedMusic> $addedMusics */
        $addedMusics = $document->addedMusics()->with('music.collections')->orderBy('id')->get();
        $addedScores = $this->scores->forMusicIds($addedMusics->pluck('music_id')->unique()->values()->all(), $viewer);

        $byMusic = [];
        $byAdded = [];
        $bySlot = [];
        $loose = [];

        $slotOfAssignment = [];

        foreach ($slots as $slot) {
            foreach ($slot['assignments'] as $assignment) {
                $slotOfAssignment[$assignment['id']] = $slot['id'];
            }
        }

        $addedIds = $addedMusics->pluck('id')->all();

        foreach ($entries->sortBy('sequence') as $entry) {
            if ($entry->added_music_id !== null && in_array($entry->added_music_id, $addedIds, true)) {
                $byAdded[$entry->added_music_id][] = $entry;

                continue;
            }

            if (isset($slotOfAssignment[$entry->music_plan_slot_assignment_id])) {
                $byMusic[$entry->music_plan_slot_assignment_id][] = $entry;

                continue;
            }

            if (in_array($entry->music_plan_slot_plan_id, $slotIds, true)) {
                $bySlot[$entry->music_plan_slot_plan_id][] = $entry;

                continue;
            }

            $loose[] = $entry;
        }

        $addedNodes = [];

        foreach ($addedMusics as $added) {
            $container = in_array($added->music_plan_slot_plan_id, $slotIds, true)
                ? 'slot:'.$added->music_plan_slot_plan_id
                : 'root';

            $addedNodes[$container][] = $this->addedMusicNode(
                $added,
                $container === 'root' ? null : $added->music_plan_slot_plan_id,
                $byAdded[$added->id] ?? [],
                $addedScores->get($added->music_id, collect())->all(),
                $chosenFiles,
                $viewer,
            );
        }

        $placed = [];
        $unplaced = [];
        $empty = [];

        foreach ($addedNodes as $container => $nodes) {
            foreach ($nodes as $node) {
                if ($node['weight'] === 0) {
                    $empty[$container][] = $node;
                }
            }
        }

        foreach ($slots as $index => $slot) {
            $node = $this->slotNode($slot, $index, $byMusic, $bySlot[$slot['id']] ?? [], $addedNodes['slot:'.$slot['id']] ?? [], $chosenFiles);

            if ($node['weight'] > 0) {
                $placed[] = ['sequence' => $node['sequence'], 'node' => $node];

                continue;
            }

            $unplaced[] = $node;
        }

        foreach ($loose as $entry) {
            $placed[] = ['sequence' => $entry->sequence, 'node' => $this->entryNode($entry)];
        }

        foreach ($addedNodes['root'] ?? [] as $node) {
            if ($node['weight'] > 0) {
                $placed[] = ['sequence' => $node['sequence'], 'node' => $node];
            }
        }

        usort($placed, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        $count = 0;
        $tree = $this->placeEmpty($this->anchor(array_column($placed, 'node'), $unplaced), 'root', $empty, $count);

        return $this->withMoves($tree);
    }

    /**
     * How many rows are printed before each of the document's own musics.
     *
     * The number an empty added music keeps its place by, read off a tree in
     * the order it would be written. Every added music is answered, not only the
     * empty ones: a music whose last row is about to go has to know where it
     * stood.
     *
     * @param  list<array<string, mixed>>  $outline
     * @return array<int, int> keyed by added music id
     */
    public function placeholders(array $outline): array
    {
        $counts = [];
        $count = 0;

        $walk = function (array $nodes) use (&$walk, &$counts, &$count): void {
            foreach ($nodes as $node) {
                if ($node['kind'] === 'entry') {
                    $count++;

                    continue;
                }

                if ($node['local'] ?? false) {
                    $counts[$node['id']] = $count;
                }

                $walk($node['children']);
            }
        };

        $walk($outline);

        return $counts;
    }

    /**
     * The entry ids in the order the booklet prints them.
     *
     * @param  list<array<string, mixed>>  $outline
     * @return list<int>
     */
    public function flatten(array $outline): array
    {
        $ids = [];

        foreach ($outline as $node) {
            if ($node['kind'] === 'entry') {
                $ids[] = $node['entry']->id;

                continue;
            }

            foreach ($this->flatten($node['children']) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Swap one node with the nearest sibling that has anything in the booklet.
     *
     * `$kind` is `entry`, `slot`, `music` or `added` — the last being a music only
     * the document holds.
     *
     * Nearest rather than next, because a slot the booklet takes nothing from
     * changes nothing about the printed order: stepping over it would look like
     * an arrow that did not work. Nothing can leave the container it is in, which
     * is the whole point — a Sanctus cannot be moved in front of the Introit, and
     * a music cannot leave its slot.
     *
     * @param  list<array<string, mixed>>  $outline
     * @return list<array<string, mixed>>|null the outline reordered, or null when there was no such move to make
     */
    public function moved(array $outline, string $kind, int $id, int $direction): ?array
    {
        $key = "{$kind}:{$id}";
        $index = null;

        foreach ($outline as $position => $node) {
            if ($this->keyOf($node) === $key) {
                $index = $position;

                break;
            }
        }

        if ($index === null) {
            foreach ($outline as $position => $node) {
                if ($node['kind'] === 'entry') {
                    continue;
                }

                $moved = $this->moved($node['children'], $kind, $id, $direction);

                if ($moved !== null) {
                    $outline[$position]['children'] = $moved;

                    return $outline;
                }
            }

            return null;
        }

        // A document's own music may be moved while it is still empty: it is
        // added at the end and has to be put in its place before a score of it
        // is chosen.
        if ($outline[$index]['weight'] === 0 && ! ($outline[$index]['local'] ?? false)) {
            return null;
        }

        $step = $direction < 0 ? -1 : 1;
        $target = $index + $step;

        while (isset($outline[$target]) && $outline[$target]['weight'] === 0) {
            $target += $step;
        }

        if (! isset($outline[$target])) {
            return null;
        }

        [$outline[$index], $outline[$target]] = [$outline[$target], $outline[$index]];

        return $outline;
    }

    /**
     * Where a new paragraph belongs in the printed order.
     *
     * Counted rather than looked up: the tree is walked in printed order, and the
     * answer is how many rows have gone by when the place asked for is reached. A
     * container the booklet has nothing in yet still has a place — the point its
     * first row would occupy — which is what lets a rubric be written under a slot
     * before any of its music has been chosen.
     *
     * @param  list<array<string, mixed>>  $outline
     */
    public function insertIndex(array $outline, ?int $slotPlanId, ?int $assignmentId, ?int $afterEntryId, ?int $addedMusicId = null): int
    {
        if ($afterEntryId !== null) {
            $position = array_search($afterEntryId, $this->flatten($outline), true);

            return $position === false ? 0 : $position + 1;
        }

        $key = $this->containerKey($slotPlanId, $assignmentId, $addedMusicId);

        // Nothing named at all: the words open the booklet.
        if ($key === null) {
            return 0;
        }

        $count = 0;
        $this->countUpTo($outline, $key, $count);

        return $count;
    }

    /**
     * Where a newly chosen score belongs in the printed order.
     *
     * At the end of the music it was chosen for, and so at the place the plan
     * gives that music — whether or not the booklet has taken anything from it
     * yet, since an empty slot and an empty music both keep the place the plan
     * gives them. That is what lets a score be turned off and on again without
     * its slot wandering to the back of the booklet. Chosen from no plan at all,
     * it goes to the end, there being nothing to say where else it belongs.
     *
     * @param  list<array<string, mixed>>  $outline
     */
    public function appendIndex(array $outline, ?int $slotPlanId, ?int $assignmentId, ?int $addedMusicId = null): int
    {
        $key = $this->containerKey($slotPlanId, $assignmentId, $addedMusicId);
        $end = count($this->flatten($outline));

        if ($key === null) {
            return $end;
        }

        $container = $this->find($outline, $key);

        if ($container === null) {
            return $end;
        }

        $count = 0;
        $this->countUpTo($outline, $key, $count);

        return $count + count($this->flatten([$container]));
    }

    /**
     * The key of the innermost container named: the document's own music, the
     * plan's music, or the slot.
     */
    private function containerKey(?int $slotPlanId, ?int $assignmentId, ?int $addedMusicId): ?string
    {
        return match (true) {
            $addedMusicId !== null => "added:{$addedMusicId}",
            $assignmentId !== null => "music:{$assignmentId}",
            $slotPlanId !== null => "slot:{$slotPlanId}",
            default => null,
        };
    }

    /**
     * Count the rows printed before the named slot or music begins.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return bool whether it was reached, which is what stops the counting
     */
    private function countUpTo(array $nodes, string $key, int &$count): bool
    {
        foreach ($nodes as $node) {
            if ($node['kind'] === 'entry') {
                $count++;

                continue;
            }

            if ($node['key'] === $key) {
                return true;
            }

            if ($this->countUpTo($node['children'], $key, $count)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The slot or music of that id, wherever in the tree it stands.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function find(array $nodes, string $key): ?array
    {
        foreach ($nodes as $node) {
            if ($node['kind'] === 'entry') {
                continue;
            }

            if ($node['key'] === $key) {
                return $node;
            }

            $found = $this->find($node['children'], $key);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The plan's slots in liturgical order, with every score the viewer may see
     * for each music — the same list the service view shows, reused whole.
     *
     * @return list<array<string, mixed>>
     */
    private function planSlots(MusicPlan $plan): array
    {
        $viewer = Auth::user();
        $scoresByMusicId = $this->scores->forViewer($plan, $viewer);

        $assignments = $plan->musicAssignments()
            ->with(['music.collections'])
            ->orderBy('music_plan_slot_plan_id')
            ->orderBy('music_sequence')
            ->get()
            ->groupBy('music_plan_slot_plan_id');

        return $plan->slots()
            ->withPivot('id', 'sequence')
            ->orderBy('music_plan_slot_plan.sequence')
            ->get()
            ->map(fn ($slot): array => [
                'id' => $slot->pivot->id,
                'name' => $slot->name,
                'assignments' => $assignments->get($slot->pivot->id, collect())
                    ->map(fn ($assignment): array => [
                        'id' => $assignment->id,
                        'music_id' => $assignment->music_id,
                        'music_title' => $assignment->music?->title,
                        'music_reference' => $assignment->music?->collectionReference($viewer),
                        'scores' => $scoresByMusicId->get($assignment->music_id, collect())->all(),
                    ])
                    ->all(),
            ])
            ->all();
    }

    /**
     * One slot: the music the booklet took from it, in the booklet's own order,
     * then the music it did not.
     *
     * @param  array<string, mixed>  $slot
     * @param  array<int, list<PlanEntry>>  $byMusic
     * @param  list<PlanEntry>  $texts
     * @param  list<array<string, mixed>>  $addedNodes  the document's own musics added in this slot
     * @param  array<int, int>  $chosenFiles
     * @return array<string, mixed>
     */
    private function slotNode(array $slot, int $planIndex, array $byMusic, array $texts, array $addedNodes, array $chosenFiles): array
    {
        $placed = [];
        $unplaced = [];

        foreach (array_values($slot['assignments']) as $index => $assignment) {
            $node = $this->musicNode($assignment, $slot['id'], $index, $byMusic[$assignment['id']] ?? [], $chosenFiles);

            if ($node['weight'] > 0) {
                $placed[] = ['sequence' => $node['sequence'], 'node' => $node];

                continue;
            }

            $unplaced[] = $node;
        }

        foreach ($texts as $entry) {
            $placed[] = ['sequence' => $entry->sequence, 'node' => $this->entryNode($entry)];
        }

        foreach ($addedNodes as $node) {
            if ($node['weight'] > 0) {
                $placed[] = ['sequence' => $node['sequence'], 'node' => $node];
            }
        }

        usort($placed, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        $children = $this->anchor(array_column($placed, 'node'), $unplaced);
        $headingEntry = $this->firstEntry($children);

        return [
            'kind' => 'slot',
            'key' => 'slot:'.$slot['id'],
            'id' => $slot['id'],
            'name' => $slot['name'],
            'planIndex' => $planIndex,
            'children' => $children,
            'weight' => $this->weigh($children),
            'sequence' => $placed === [] ? PHP_INT_MAX : $placed[0]['sequence'],
            // The row that speaks this slot's name, and whether it is speaking
            // it: the plan puts the switch beside the name, but the choice is
            // the opening row's.
            'headingEntryId' => $headingEntry?->id,
            'showsName' => ! $headingEntry instanceof PlanEntry || $headingEntry->show_slot,
        ];
    }

    /**
     * One music: what the booklet prints of it, then what it could still print.
     *
     * @param  array<string, mixed>  $assignment
     * @param  list<PlanEntry>  $entries
     * @param  array<int, int>  $chosenFiles
     * @return array<string, mixed>
     */
    private function musicNode(array $assignment, int $slotPlanId, int $planIndex, array $entries, array $chosenFiles): array
    {
        $children = array_map(fn (PlanEntry $entry): array => $this->entryNode($entry), $entries);
        $headingEntry = $entries[0] ?? null;

        return [
            'kind' => 'music',
            'key' => 'music:'.$assignment['id'],
            'local' => false,
            'id' => $assignment['id'],
            'slotId' => $slotPlanId,
            'planIndex' => $planIndex,
            'musicId' => $assignment['music_id'],
            'title' => $assignment['music_title'],
            'reference' => $assignment['music_reference'],
            'children' => $children,
            'offers' => $this->offers($assignment['scores'], ...$this->taken($entries, $chosenFiles)),
            'weight' => count($children),
            'sequence' => $entries === [] ? PHP_INT_MAX : $entries[0]->sequence,
            // The row that speaks this music's own name, and whether it is: the
            // switch stands beside the name in the plan, the choice is the row's.
            'headingEntryId' => $headingEntry?->id,
            'showsName' => ! $headingEntry instanceof PlanEntry || $headingEntry->show_music_title,
            // Where the music can be looked up is asked for rather than kept
            // off, and the answer is the opening row's, like the names above it.
            'showsReference' => $headingEntry instanceof PlanEntry && $headingEntry->show_collections,
        ];
    }

    /**
     * One music the document holds and its plan does not.
     *
     * Shaped exactly like a plan's music node, so the panes and the remote draw
     * it without a case of their own — `local` is what tells it apart, and the
     * `id` is the added music's rather than an assignment's.
     *
     * @param  list<PlanEntry>  $entries
     * @param  list<array<string, mixed>>  $scores
     * @param  array<int, int>  $chosenFiles
     * @return array<string, mixed>
     */
    private function addedMusicNode(PlanAddedMusic $added, ?int $slotPlanId, array $entries, array $scores, array $chosenFiles, ?User $viewer): array
    {
        $children = array_map(fn (PlanEntry $entry): array => $this->entryNode($entry), $entries);
        $headingEntry = $entries[0] ?? null;

        return [
            'kind' => 'music',
            'key' => 'added:'.$added->id,
            'local' => true,
            'id' => $added->id,
            'slotId' => $slotPlanId,
            'planIndex' => null,
            'musicId' => $added->music_id,
            'title' => $added->music?->title,
            'reference' => $added->music?->collectionReference($viewer),
            'children' => $children,
            'offers' => $this->offers($scores, ...$this->taken($entries, $chosenFiles)),
            'weight' => count($children),
            'sequence' => $entries === [] ? $added->sequence : $entries[0]->sequence,
            'placeholder' => $added->sequence,
            'headingEntryId' => $headingEntry?->id,
            'showsName' => ! $headingEntry instanceof PlanEntry || $headingEntry->show_music_title,
            'showsReference' => $headingEntry instanceof PlanEntry && $headingEntry->show_collections,
        ];
    }

    /**
     * Put the document's own musics that hold nothing yet where they were left.
     *
     * They have no row to stand behind and no place in the plan, so each keeps
     * the number of rows printed before it, and is put back in front of the first
     * thing that comes after that many rows — inside the container it belongs to
     * and nowhere else.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, list<array<string, mixed>>>  $empty  keyed by container: `root` or `slot:<id>`
     * @return list<array<string, mixed>>
     */
    private function placeEmpty(array $nodes, string $container, array $empty, int &$count): array
    {
        $waiting = $empty[$container] ?? [];
        usort($waiting, fn (array $a, array $b): int => $a['placeholder'] <=> $b['placeholder']);

        $result = [];

        foreach ($nodes as $node) {
            while ($waiting !== [] && $waiting[0]['placeholder'] <= $count) {
                $result[] = array_shift($waiting);
            }

            if ($node['kind'] === 'entry') {
                $count++;
            } elseif ($node['kind'] === 'slot') {
                $node['children'] = $this->placeEmpty($node['children'], $node['key'], $empty, $count);
            } else {
                $count += $node['weight'];
            }

            $result[] = $node;
        }

        return [...$result, ...$waiting];
    }

    /**
     * What this one music has already taken — its own rows, and nobody else's.
     *
     * Asked per music rather than of the whole document, and that is the point.
     * A service may sing the same music twice: the plan assigns it to two slots,
     * or the document is given it twice as a music of its own. Weighed against
     * everything the document holds, the second occurrence would find its only
     * score already taken by the first and offer nothing at all — an "Add" button
     * missing from a music that visibly has a score, and no way to put it on the
     * page a second time.
     *
     * Which file a row draws is the document's answer, since a row naming no file
     * draws the score's default one; it is handed in keyed by row, and only the
     * rows of this music are read out of it.
     *
     * @param  list<PlanEntry>  $entries
     * @param  array<int, int>  $chosenFiles  file id, keyed by entry id
     * @return array{list<int>, list<int>} the score ids taken, and the file ids
     */
    private function taken(array $entries, array $chosenFiles): array
    {
        $scoreIds = [];
        $fileIds = [];

        foreach ($entries as $entry) {
            if ($entry->score_id !== null) {
                $scoreIds[] = $entry->score_id;
            }

            if (isset($chosenFiles[$entry->id])) {
                $fileIds[] = $chosenFiles[$entry->id];
            }
        }

        return [$scoreIds, $fileIds];
    }

    /**
     * What of this music's scores the booklet has not taken.
     *
     * An uploaded score holding several files is not one thing to take or leave —
     * the projection slide and the accompaniment are different music on the page —
     * so where there is a choice the score is only a label and each file is
     * offered on its own line. A score every file of which this music already
     * prints is not offered at all.
     *
     * @param  list<array<string, mixed>>  $scores
     * @param  list<int>  $chosenScoreIds
     * @param  list<int>  $chosenFileIds
     * @return list<array<string, mixed>>
     */
    private function offers(array $scores, array $chosenScoreIds, array $chosenFileIds): array
    {
        $offers = [];

        foreach ($scores as $score) {
            $files = $score['files'] ?? [];

            if (count($files) > 1) {
                $left = array_values(array_filter(
                    $files,
                    fn (array $file): bool => ! in_array($file['id'], $chosenFileIds, true),
                ));

                if ($left === []) {
                    continue;
                }

                $offers[] = ['score' => $score, 'files' => $left];

                continue;
            }

            if (in_array($score['id'], $chosenScoreIds, true)) {
                continue;
            }

            $offers[] = ['score' => $score, 'files' => []];
        }

        return $offers;
    }

    /**
     * @return array<string, mixed>
     */
    private function entryNode(PlanEntry $entry): array
    {
        return ['kind' => 'entry', 'entry' => $entry, 'weight' => 1];
    }

    /**
     * The first row printed under these nodes — the one that speaks the slot's
     * name, or the music's, and so the one whose switch turns that name on and
     * off. Null where nothing has been chosen yet.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private function firstEntry(array $nodes): ?PlanEntry
    {
        foreach ($nodes as $node) {
            if ($node['kind'] === 'entry') {
                return $node['entry'];
            }

            $found = $this->firstEntry($node['children']);

            if ($found instanceof PlanEntry) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Put the slots — or the musics — the booklet takes nothing from back where
     * the plan has them.
     *
     * They have no row to stand behind, so they stand in front of the first thing
     * the plan puts after them, which keeps the pane readable as the service even
     * where half of it has not been chosen from. It is also what gives an empty
     * slot a place for a score to be added at: turning one off and on again
     * leaves the booklet in the order it was.
     *
     * In front of rather than behind, because words written at the head of a slot
     * — or of the booklet — are a row and not a plan node, and would otherwise be
     * pushed under every part that has nothing chosen from it. Nothing empty is
     * printed, so which side of a paragraph it waits on changes only the pane.
     *
     * @param  list<array<string, mixed>>  $ordered
     * @param  list<array<string, mixed>>  $empty
     * @return list<array<string, mixed>>
     */
    private function anchor(array $ordered, array $empty): array
    {
        foreach ($empty as $node) {
            $at = count($ordered);

            foreach ($ordered as $index => $placed) {
                if ($placed['kind'] === $node['kind'] && ! ($placed['local'] ?? false) && $placed['planIndex'] > $node['planIndex']) {
                    $at = $index;

                    break;
                }
            }

            array_splice($ordered, $at, 0, [$node]);
        }

        return $ordered;
    }

    /**
     * Say of each slot and music whether it has anywhere to go.
     *
     * The arrows a row of the booklet carries are greyed by the stylesheet
     * instead, since a row is a component of its own and hears nothing of where
     * it has ended up.
     *
     * A document's own music may move while it is empty, the one node that may:
     * see moved().
     *
     * @param  list<array<string, mixed>>  $children
     * @return list<array<string, mixed>>
     */
    private function withMoves(array $children): array
    {
        foreach ($children as $index => $node) {
            if ($node['kind'] === 'entry') {
                continue;
            }

            $before = array_slice($children, 0, $index);
            $after = array_slice($children, $index + 1);
            $movable = $node['weight'] > 0 || ($node['local'] ?? false);

            $children[$index]['children'] = $this->withMoves($node['children']);
            $children[$index]['canMoveUp'] = $movable && $this->weigh($before) > 0;
            $children[$index]['canMoveDown'] = $movable && $this->weigh($after) > 0;
        }

        return $children;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function weigh(array $nodes): int
    {
        return array_sum(array_column($nodes, 'weight'));
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function keyOf(array $node): string
    {
        return $node['kind'] === 'entry' ? 'entry:'.$node['entry']->id : $node['key'];
    }
}
