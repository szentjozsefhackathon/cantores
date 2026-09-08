<?php

namespace App\Services;

use App\Models\Booklet;
use App\Models\BookletScore;
use App\Models\MusicPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The booklet seen as the plan it was made from.
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
 * booklet without the plan hearing about it; a slot holding nothing keeps the
 * place the plan gives it, since it has nothing to be moved. Everything the
 * booklet holds comes before everything it merely could hold, so what is read
 * downwards is the booklet, and the rest of the plan waits underneath it.
 *
 * The order the tree is walked in *is* the printed order: flatten() is what the
 * booklet's sequences are then written from, which is what keeps the two from
 * ever drifting apart.
 */
class BookletOutline
{
    public function __construct(private MusicPlanScoreListService $scores) {}

    /**
     * The whole left-hand pane: slots, the music in them, and what of it the
     * booklet took.
     *
     * The entries are handed in rather than read again — the editor already holds
     * them, and reads them once for everything it draws.
     *
     * @param  Collection<int, BookletScore>  $entries
     * @param  list<int>  $chosenScoreIds
     * @param  list<int>  $chosenFileIds
     * @return list<array<string, mixed>>
     */
    public function for(Booklet $booklet, Collection $entries, array $chosenScoreIds = [], array $chosenFileIds = []): array
    {
        $plan = $booklet->musicPlan;
        $slots = $plan instanceof MusicPlan ? $this->planSlots($plan) : [];

        $byMusic = [];
        $bySlot = [];
        $loose = [];

        $slotOfAssignment = [];

        foreach ($slots as $slot) {
            foreach ($slot['assignments'] as $assignment) {
                $slotOfAssignment[$assignment['id']] = $slot['id'];
            }
        }

        $slotIds = array_column($slots, 'id');

        foreach ($entries->sortBy('sequence') as $entry) {
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

        $placed = [];
        $unplaced = [];

        foreach ($slots as $index => $slot) {
            $node = $this->slotNode($slot, $index, $byMusic, $bySlot[$slot['id']] ?? [], $chosenScoreIds, $chosenFileIds);

            if ($node['weight'] > 0) {
                $placed[] = ['sequence' => $node['sequence'], 'node' => $node];

                continue;
            }

            $unplaced[] = $node;
        }

        foreach ($loose as $entry) {
            $placed[] = ['sequence' => $entry->sequence, 'node' => $this->entryNode($entry)];
        }

        usort($placed, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        return $this->withMoves($this->anchor(array_column($placed, 'node'), $unplaced));
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
        $index = null;

        foreach ($outline as $position => $node) {
            if ($node['kind'] === $kind && $this->idOf($node) === $id) {
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

        if ($outline[$index]['weight'] === 0) {
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
    public function insertIndex(array $outline, ?int $slotPlanId, ?int $assignmentId, ?int $afterEntryId): int
    {
        if ($afterEntryId !== null) {
            $position = array_search($afterEntryId, $this->flatten($outline), true);

            return $position === false ? 0 : $position + 1;
        }

        // Nothing named at all: the words open the booklet.
        if ($slotPlanId === null && $assignmentId === null) {
            return 0;
        }

        $count = 0;
        $this->countUpTo($outline, $assignmentId === null ? 'slot' : 'music', $assignmentId ?? $slotPlanId, $count);

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
    public function appendIndex(array $outline, ?int $slotPlanId, ?int $assignmentId): int
    {
        $kind = $assignmentId === null ? 'slot' : 'music';
        $id = $assignmentId ?? $slotPlanId;
        $end = count($this->flatten($outline));

        if ($id === null) {
            return $end;
        }

        $container = $this->find($outline, $kind, $id);

        if ($container === null) {
            return $end;
        }

        $count = 0;
        $this->countUpTo($outline, $kind, $id, $count);

        return $count + count($this->flatten([$container]));
    }

    /**
     * Count the rows printed before the named slot or music begins.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return bool whether it was reached, which is what stops the counting
     */
    private function countUpTo(array $nodes, string $kind, int $id, int &$count): bool
    {
        foreach ($nodes as $node) {
            if ($node['kind'] === 'entry') {
                $count++;

                continue;
            }

            if ($node['kind'] === $kind && $node['id'] === $id) {
                return true;
            }

            if ($this->countUpTo($node['children'], $kind, $id, $count)) {
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
    private function find(array $nodes, string $kind, int $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['kind'] === 'entry') {
                continue;
            }

            if ($node['kind'] === $kind && $node['id'] === $id) {
                return $node;
            }

            $found = $this->find($node['children'], $kind, $id);

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
            ->with(['music'])
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
     * @param  array<int, list<BookletScore>>  $byMusic
     * @param  list<BookletScore>  $texts
     * @param  list<int>  $chosenScoreIds
     * @param  list<int>  $chosenFileIds
     * @return array<string, mixed>
     */
    private function slotNode(array $slot, int $planIndex, array $byMusic, array $texts, array $chosenScoreIds, array $chosenFileIds): array
    {
        $placed = [];
        $unplaced = [];

        foreach (array_values($slot['assignments']) as $index => $assignment) {
            $node = $this->musicNode($assignment, $slot['id'], $index, $byMusic[$assignment['id']] ?? [], $chosenScoreIds, $chosenFileIds);

            if ($node['weight'] > 0) {
                $placed[] = ['sequence' => $node['sequence'], 'node' => $node];

                continue;
            }

            $unplaced[] = $node;
        }

        foreach ($texts as $entry) {
            $placed[] = ['sequence' => $entry->sequence, 'node' => $this->entryNode($entry)];
        }

        usort($placed, fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        $children = $this->anchor(array_column($placed, 'node'), $unplaced);

        return [
            'kind' => 'slot',
            'id' => $slot['id'],
            'name' => $slot['name'],
            'planIndex' => $planIndex,
            'children' => $this->withMoves($children),
            'weight' => $this->weigh($children),
            'sequence' => $placed === [] ? PHP_INT_MAX : $placed[0]['sequence'],
        ];
    }

    /**
     * One music: what the booklet prints of it, then what it could still print.
     *
     * @param  array<string, mixed>  $assignment
     * @param  list<BookletScore>  $entries
     * @param  list<int>  $chosenScoreIds
     * @param  list<int>  $chosenFileIds
     * @return array<string, mixed>
     */
    private function musicNode(array $assignment, int $slotPlanId, int $planIndex, array $entries, array $chosenScoreIds, array $chosenFileIds): array
    {
        $children = array_map(fn (BookletScore $entry): array => $this->entryNode($entry), $entries);

        return [
            'kind' => 'music',
            'id' => $assignment['id'],
            'slotId' => $slotPlanId,
            'planIndex' => $planIndex,
            'musicId' => $assignment['music_id'],
            'title' => $assignment['music_title'],
            'children' => $this->withMoves($children),
            'offers' => $this->offers($assignment['scores'], $chosenScoreIds, $chosenFileIds),
            'weight' => count($children),
            'sequence' => $entries === [] ? PHP_INT_MAX : $entries[0]->sequence,
        ];
    }

    /**
     * What of this music's scores the booklet has not taken.
     *
     * An uploaded score holding several files is not one thing to take or leave —
     * the projection slide and the accompaniment are different music on the page —
     * so where there is a choice the score is only a label and each file is
     * offered on its own line. A score every file of which is already in the
     * booklet is not offered at all.
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
    private function entryNode(BookletScore $entry): array
    {
        return ['kind' => 'entry', 'entry' => $entry, 'weight' => 1];
    }

    /**
     * Put the slots — or the musics — the booklet takes nothing from back where
     * the plan has them.
     *
     * They have no row to stand behind, so they stand behind the last thing the
     * plan puts before them, which keeps the pane readable as the service even
     * where half of it has not been chosen from. It is also what gives an empty
     * slot a place for a score to be added at: turning one off and on again
     * leaves the booklet in the order it was.
     *
     * @param  list<array<string, mixed>>  $ordered
     * @param  list<array<string, mixed>>  $empty
     * @return list<array<string, mixed>>
     */
    private function anchor(array $ordered, array $empty): array
    {
        foreach ($empty as $node) {
            $at = 0;

            foreach ($ordered as $index => $placed) {
                if ($placed['kind'] === $node['kind'] && $placed['planIndex'] < $node['planIndex']) {
                    $at = $index + 1;
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

            $children[$index]['canMoveUp'] = $node['weight'] > 0 && $this->weigh($before) > 0;
            $children[$index]['canMoveDown'] = $node['weight'] > 0 && $this->weigh($after) > 0;
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
    private function idOf(array $node): ?int
    {
        return $node['kind'] === 'entry' ? $node['entry']->id : $node['id'];
    }
}
