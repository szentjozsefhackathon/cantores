<?php

namespace App\Services;

use App\Contracts\PlanAddedMusic;
use App\Contracts\PlanDocument;
use App\Contracts\PlanEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Writing a document's order down.
 *
 * PlanOutline says what the order is; this is the one place it is stored. Both
 * editors, the score toggle and the remote write through it, so the rule that a
 * move is made on the tree and the sequences are read back off it lives once.
 *
 * Two things are written. Every row gets its position in the flattened tree.
 * Every music the document holds on its own gets the number of rows printed
 * before it — which is only ever read while it holds no row, and is the one way
 * such a music can keep its place in the document before a score of it has been
 * chosen.
 */
class PlanOrder
{
    public function __construct(private readonly PlanOutline $outline) {}

    /**
     * The tree the order is read from, built afresh from the database.
     *
     * @return list<array<string, mixed>>
     */
    public function outlineOf(PlanDocument $document): array
    {
        return $this->outline->for($document, $document->entries()->get());
    }

    /**
     * Move one node past the nearest sibling with anything in it.
     *
     * @param  string  $kind  `entry`, `slot`, `music` or `added`
     * @param  list<array<string, mixed>>|null  $outline  the tree already in hand, if there is one
     * @return bool whether anything moved — false is a move PlanOutline refuses
     */
    public function move(PlanDocument $document, string $kind, int $id, int $direction, ?array $outline = null): bool
    {
        $moved = $this->outline->moved($outline ?? $this->outlineOf($document), $kind, $id, $direction);

        if ($moved === null) {
            return false;
        }

        $this->write($document, $this->outline->flatten($moved), $this->outline->placeholders($moved));

        return true;
    }

    /**
     * Square the stored order up with the tree, where they have drifted apart.
     *
     * @param  list<array<string, mixed>>  $outline
     * @param  list<int>  $storedEntryIds  the rows in the order they are stored in
     * @return bool whether anything was written
     */
    public function normalize(PlanDocument $document, array $outline, array $storedEntryIds): bool
    {
        $ordered = $this->outline->flatten($outline);
        $placeholders = $this->outline->placeholders($outline);

        $stored = $document->addedMusics()->pluck('sequence', 'id')->all();

        if ($ordered === $storedEntryIds && array_diff_assoc($placeholders, $stored) === []) {
            return false;
        }

        $this->write($document, $ordered, $placeholders);

        return true;
    }

    /**
     * Put a row just created at the given place in the order.
     *
     * A music waiting empty further down keeps its place by moving along one; one
     * standing exactly there stays in front of the new row, which is what the
     * tree does with it on a tie.
     *
     * @param  list<array<string, mixed>>  $outline  the tree before the row existed
     */
    public function insert(PlanDocument $document, array $outline, int $entryId, int $at): void
    {
        $order = $this->outline->flatten($outline);
        array_splice($order, $at, 0, [$entryId]);

        $placeholders = array_map(
            fn (int $count): int => $count > $at ? $count + 1 : $count,
            $this->outline->placeholders($outline),
        );

        $this->write($document, $order, $placeholders);
    }

    /**
     * Delete rows, and let every music waiting after them close up.
     *
     * A music whose last row this was keeps the place that row had.
     *
     * @param  list<array<string, mixed>>  $outline  the tree while the rows still exist
     * @param  list<int>  $entryIds
     */
    public function remove(PlanDocument $document, array $outline, array $entryIds): void
    {
        $document->entries()->whereKey($entryIds)->get()->each(fn (Model $entry): ?bool => $entry->delete());

        $this->closeUp($document, $outline, $entryIds);
    }

    /**
     * Delete one of the document's own musics, and every row chosen from it.
     *
     * @param  list<array<string, mixed>>  $outline  the tree while the music still exists
     */
    public function removeAddedMusic(PlanDocument $document, array $outline, PlanAddedMusic&Model $music): void
    {
        $entryIds = $music->entries()->pluck('id')->all();

        $music->delete();

        $this->closeUp($document, $outline, $entryIds);
    }

    /**
     * @param  list<int>  $entryIds
     * @param  array<int, int>  $placeholders  keyed by added music id
     */
    public function write(PlanDocument $document, array $entryIds, array $placeholders): void
    {
        $entries = $document->entries()->get()->keyBy('id');

        foreach ($entryIds as $position => $id) {
            $entry = $entries->get($id);

            if ($entry instanceof PlanEntry && $entry instanceof Model) {
                $entry->update(['sequence' => $position]);
            }
        }

        $musics = $document->addedMusics()->get()->keyBy('id');

        foreach ($placeholders as $id => $count) {
            $music = $musics->get($id);

            if ($music instanceof PlanAddedMusic && $music instanceof Model) {
                $music->update(['sequence' => $count]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $outline
     * @param  list<int>  $removedEntryIds
     */
    private function closeUp(PlanDocument $document, array $outline, array $removedEntryIds): void
    {
        $order = $this->outline->flatten($outline);

        $removedPositions = array_keys(array_filter(
            $order,
            fn (int $id): bool => in_array($id, $removedEntryIds, true),
        ));

        $placeholders = array_map(
            fn (int $count): int => $count - count(array_filter($removedPositions, fn (int $position): bool => $position < $count)),
            $this->outline->placeholders($outline),
        );

        $remaining = array_values(array_filter(
            $order,
            fn (int $id): bool => ! in_array($id, $removedEntryIds, true),
        ));

        $this->write($document, $remaining, $placeholders);
    }
}
