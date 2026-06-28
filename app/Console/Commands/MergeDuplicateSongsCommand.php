<?php

namespace App\Console\Commands;

use App\Models\Music;
use App\Models\MusicRelation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class MergeDuplicateSongsCommand extends Command
{
    protected $signature = 'cantores:merge-duplicate-songs
                            {--dry-run : Report the merges that would happen without changing anything}';

    protected $description = 'Merge duplicate songs that belong to the exact same set of collections.';

    /**
     * Scalar music columns whose value the survivor may absorb from a duplicate
     * when the survivor leaves them empty.
     *
     * @var list<string>
     */
    private const ABSORBABLE_FIELDS = ['subtitle', 'custom_id', 'import_batch_number'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $groups = $this->duplicateGroups();

        if ($groups->isEmpty()) {
            $this->info('No duplicate songs found.');

            return self::SUCCESS;
        }

        $mergedCount = 0;

        foreach ($groups as $group) {
            /** @var Music $survivor */
            $survivor = $group->shift();
            $losers = $group;

            $this->line(sprintf(
                '#%d "%s" <= %s',
                $survivor->id,
                $survivor->title,
                $losers->map(fn (Music $m) => "#{$m->id} \"{$m->title}\"")->implode(', '),
            ));

            if (! $dryRun) {
                DB::transaction(function () use ($survivor, $losers) {
                    foreach ($losers as $loser) {
                        $this->mergeInto($survivor, $loser);
                    }
                });
            }

            $mergedCount += $losers->count();
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d duplicate%s across %d group%s.',
            $dryRun ? 'Would merge' : 'Merged',
            $mergedCount,
            $mergedCount === 1 ? '' : 's',
            $groups->count(),
            $groups->count() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * Group songs that share the exact same set of collections, ordered so the
     * oldest (lowest id) song is first in each group. Only groups with more than
     * one song are returned.
     *
     * @return \Illuminate\Support\Collection<int, EloquentCollection<int, Music>>
     */
    private function duplicateGroups(): \Illuminate\Support\Collection
    {
        return Music::query()
            ->with('collections')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Music $music) => $this->collectionKey($music))
            ->reject(fn (EloquentCollection $group, string $key) => $key === '' || $group->count() < 2)
            ->values();
    }

    /**
     * A stable key describing the song's collection memberships by collection
     * and order number. Two songs are duplicates only when they appear under the
     * exact same set of collection/number pairs.
     *
     * Memberships without an order number are ignored: an empty number is not a
     * reliable duplicate signal (many distinct songs share a collection without
     * a number). A song left with no numbered membership gets an empty key and
     * is never grouped.
     */
    private function collectionKey(Music $music): string
    {
        return $music->collections
            ->filter(fn ($collection) => filled($collection->pivot->order_number))
            ->map(fn ($collection) => $collection->id.':'.$collection->pivot->order_number)
            ->unique()
            ->sort()
            ->values()
            ->implode('|');
    }

    /**
     * Merge a duplicate song into the survivor, then delete the duplicate.
     */
    private function mergeInto(Music $survivor, Music $loser): void
    {
        $this->absorbScalarFields($survivor, $loser);
        $this->absorbCollectionPivots($survivor, $loser);
        $this->mergeAuthors($survivor, $loser);
        $this->mergeScriptureReferences($survivor, $loser);
        $this->mergeUrls($survivor, $loser);
        $this->mergeMusicRelations($survivor, $loser);

        $survivor->genres()->syncWithoutDetaching($loser->genres->pluck('id')->all());
        $survivor->tags()->syncWithoutDetaching($loser->tags->pluck('id')->all());

        $loser->scores()->update(['music_id' => $survivor->id]);
        $loser->musicPlanSlotAssignments()->update(['music_id' => $survivor->id]);
        $loser->verifications()->update(['music_id' => $survivor->id]);

        $survivor->save();
        $loser->delete();
    }

    /**
     * Fill empty survivor fields with the duplicate's values; on a conflict the
     * survivor (the older record) keeps its own value.
     */
    private function absorbScalarFields(Music $survivor, Music $loser): void
    {
        foreach (self::ABSORBABLE_FIELDS as $field) {
            if (blank($survivor->{$field}) && filled($loser->{$field})) {
                $survivor->{$field} = $loser->{$field};
            }
        }
    }

    /**
     * Both songs share the same collections; absorb the duplicate's page and
     * order numbers wherever the survivor left them empty.
     */
    private function absorbCollectionPivots(Music $survivor, Music $loser): void
    {
        foreach ($loser->collections as $collection) {
            $survivorPivot = $survivor->collections->firstWhere('id', $collection->id)?->pivot;
            if ($survivorPivot === null) {
                continue;
            }

            $update = [];
            if (blank($survivorPivot->order_number) && filled($collection->pivot->order_number)) {
                $update['order_number'] = $collection->pivot->order_number;
            }
            if (blank($survivorPivot->page_number) && filled($collection->pivot->page_number)) {
                $update['page_number'] = $collection->pivot->page_number;
            }

            if ($update !== []) {
                $survivor->collections()->updateExistingPivot($collection->id, $update);
            }
        }
    }

    /**
     * Repoint author links, absorbing the author_type where the survivor's link
     * has none. The duplicate's leftover links are removed when it is deleted.
     */
    private function mergeAuthors(Music $survivor, Music $loser): void
    {
        foreach ($loser->authors as $author) {
            $survivorPivot = $survivor->authors->firstWhere('id', $author->id)?->pivot;
            $loserType = $author->pivot->author_type?->value;

            if ($survivorPivot === null) {
                $survivor->authors()->attach($author->id, [
                    'author_type' => $loserType,
                    'user_id' => $author->pivot->user_id,
                ]);
            } elseif ($survivorPivot->author_type === null && $loserType !== null) {
                $survivor->authors()->updateExistingPivot($author->id, ['author_type' => $loserType]);
            }
        }

        $survivor->load('authors');
    }

    /**
     * Repoint scripture references the survivor does not already have; the rest
     * are removed with the duplicate.
     */
    private function mergeScriptureReferences(Music $survivor, Music $loser): void
    {
        $existing = $survivor->scriptureReferences->pluck('reference')->all();

        foreach ($loser->scriptureReferences as $reference) {
            if (! in_array($reference->reference, $existing, true)) {
                $reference->update(['music_id' => $survivor->id]);
            }
        }
    }

    /**
     * Repoint URLs the survivor does not already have (matched by url).
     */
    private function mergeUrls(Music $survivor, Music $loser): void
    {
        $existing = $survivor->urls->pluck('url')->all();

        foreach ($loser->urls as $url) {
            if (! in_array($url->url, $existing, true)) {
                $url->update(['music_id' => $survivor->id]);
            }
        }
    }

    /**
     * Repoint music relations in both directions, dropping any that would become
     * a self-relation or a duplicate of a relation the survivor already has.
     */
    private function mergeMusicRelations(Music $survivor, Music $loser): void
    {
        foreach ($loser->directMusicRelations as $relation) {
            $this->repointRelation($survivor, $relation, 'music_id', 'related_music_id');
        }

        foreach ($loser->inverseMusicRelations as $relation) {
            $this->repointRelation($survivor, $relation, 'related_music_id', 'music_id');
        }
    }

    /**
     * Move one relation's $sideColumn onto the survivor unless it would point at
     * itself or duplicate an existing relation.
     */
    private function repointRelation(Music $survivor, MusicRelation $relation, string $sideColumn, string $otherColumn): void
    {
        if ((int) $relation->{$otherColumn} === $survivor->id) {
            $relation->delete();

            return;
        }

        $exists = MusicRelation::query()
            ->where($sideColumn, $survivor->id)
            ->where($otherColumn, $relation->{$otherColumn})
            ->exists();

        if ($exists) {
            $relation->delete();

            return;
        }

        $relation->update([$sideColumn => $survivor->id]);
    }
}
