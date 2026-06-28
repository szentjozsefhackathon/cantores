<?php

namespace App\Services;

use App\AuthorType;
use App\Models\Author;
use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicScriptureReference;
use App\Models\User;
use App\ScriptureReferenceType;
use App\Support\TitleSimilarity;
use Illuminate\Support\Facades\DB;

/**
 * Imports énekszámok songs/authors/songbook numbers and detects when a row would
 * merge into a song whose title is too different (a likely input error).
 *
 * A single incremental walk powers both the dry-run audit and the real import:
 * rows are processed in order while an in-memory index of matchable songs (seeded
 * from the database and grown with songs created during the run) keeps later rows
 * matching earlier ones. Matches whose title similarity is below the threshold are
 * "conflicts" requiring an explicit merge/separate decision.
 */
class EnekszamokImporter
{
    /**
     * CSV column => collection abbreviation for single-number songbooks.
     *
     * @var array<string, string>
     */
    private const SINGLE_BOOKS = [
        'Sárga' => 'SK',
        'Zöld' => 'ZK',
        'Barna' => 'BK',
        'Emmánuel Jézus Él' => 'JÉL',
        'DÚR' => 'DÚR',
        'Szent András' => 'SZTA',
    ];

    /**
     * Merged songbooks: abbreviation => [old column, new column, pad-new-to-3].
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    private const MERGED_BOOKS = [
        'KÉK' => ['Kék Régi', 'Kék Új', true],
        'TORG' => ['Téglás Régi', 'Téglás Új', false],
    ];

    /** @var array<string, Author> */
    private array $authorCache = [];

    /** @var array<string, Collection> */
    private array $collections = [];

    /** @var array<string, string> decision key => 'merge'|'separate' */
    private array $decisions = [];

    /**
     * Matchable songs grouped by collection abbreviation and order number.
     *
     * @var array<string, array<string, list<object{music: ?Music, id: ?int, title: string}>>>
     */
    private array $index = [];

    /** @var array<string, array<string, bool>> abbr => number => DB queried */
    private array $queried = [];

    public function __construct(private float $threshold = 0.55) {}

    /**
     * Required collection abbreviations for the import to run.
     *
     * @return list<string>
     */
    public static function requiredCollections(): array
    {
        return array_merge(array_values(self::SINGLE_BOOKS), array_keys(self::MERGED_BOOKS));
    }

    /**
     * Detect conflicts without writing anything.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, Collection>  $collections
     * @param  array<string, string>  $decisions
     * @return array{created: int, updated: int, duplicates: int, conflicts: array<string, array{row_title: string, existing_id: ?int, existing_title: string, matched_via: list<string>, similarity: float, decision: ?string}>, undecided: int}
     */
    public function audit(array $rows, array $collections, array $decisions): array
    {
        return $this->walk($rows, $collections, $decisions, null);
    }

    /**
     * Apply the import inside a transaction.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, Collection>  $collections
     * @param  array<string, string>  $decisions
     * @return array{created: int, updated: int, duplicates: int, conflicts: array<string, mixed>, undecided: int}
     */
    public function import(array $rows, array $collections, array $decisions, User $user): array
    {
        return DB::transaction(fn (): array => $this->walk($rows, $collections, $decisions, $user));
    }

    /**
     * Process every row in order, matching, classifying conflicts and (when a user
     * is given) writing changes.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, Collection>  $collections
     * @param  array<string, string>  $decisions
     */
    private function walk(array $rows, array $collections, array $decisions, ?User $user): array
    {
        $this->collections = $collections;
        $this->decisions = $decisions;
        $this->index = [];
        $this->queried = [];

        $apply = $user !== null;
        $created = 0;
        $updated = 0;
        $duplicates = 0;
        $undecided = 0;
        $conflicts = [];

        foreach ($rows as $row) {
            $title = trim($row['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $numbers = $this->computeNumbers($row);
            $matches = $this->lookupMatches($row);

            $targets = [];
            foreach ($matches as $match) {
                $entry = $match['entry'];
                $ratio = TitleSimilarity::ratio($title, $entry->title);

                if ($ratio >= $this->threshold) {
                    $targets[] = $entry;

                    continue;
                }

                $key = $this->decisionKey($title, $entry);
                $decision = $this->decisions[$key] ?? null;

                if (! isset($conflicts[$key])) {
                    $conflicts[$key] = [
                        'row_title' => $title,
                        'existing_id' => $entry->id,
                        'existing_title' => $entry->title,
                        'matched_via' => [],
                        'similarity' => $ratio,
                        'decision' => $decision,
                    ];
                }
                $conflicts[$key]['matched_via'] = array_values(array_unique(
                    array_merge($conflicts[$key]['matched_via'], $match['via']),
                ));

                if ($decision === 'merge') {
                    $targets[] = $entry;
                } elseif ($decision !== 'separate') {
                    $undecided++;
                }
            }

            $targets = $this->unique($targets);

            if ($targets === []) {
                $entry = (object) ['music' => null, 'id' => null, 'title' => $title];
                if ($apply) {
                    $music = Music::create([
                        'title' => $title,
                        'subtitle' => trim($row['original_title'] ?? '') ?: null,
                        'user_id' => $user->id,
                        'is_private' => false,
                    ]);
                    $entry->music = $music;
                    $entry->id = $music->id;
                    $this->applyRow($music, $row, $numbers, $user->id);
                }

                if ($matches === []) {
                    $created++;
                } else {
                    $duplicates++;
                }
                $this->indexEntry($entry, $numbers);

                continue;
            }

            foreach ($targets as $entry) {
                if ($apply && $entry->music !== null) {
                    $this->applyRow($entry->music, $row, $numbers, $user->id);
                }
                $this->indexEntry($entry, $numbers);
            }
            $updated++;
        }

        return compact('created', 'updated', 'duplicates', 'conflicts', 'undecided');
    }

    /**
     * Find every indexed song matching any of the row's candidate numbers.
     *
     * @param  array<string, string>  $row
     * @return list<array{entry: object, via: list<string>}>
     */
    private function lookupMatches(array $row): array
    {
        $byId = [];
        foreach ($this->matchCandidates($row) as $abbr => $candidates) {
            foreach ($candidates as $number) {
                foreach ($this->bucket($abbr, $number) as $entry) {
                    $oid = spl_object_id($entry);
                    if (! isset($byId[$oid])) {
                        $byId[$oid] = ['entry' => $entry, 'via' => []];
                    }
                    $byId[$oid]['via'][] = "{$abbr} {$number}";
                }
            }
        }

        return array_values($byId);
    }

    /**
     * Entries for a collection + order number, querying the database once per pair
     * and merging in any songs created earlier in this run.
     *
     * @return list<object{music: ?Music, id: ?int, title: string}>
     */
    private function bucket(string $abbr, string $number): array
    {
        if (! ($this->queried[$abbr][$number] ?? false)) {
            $this->queried[$abbr][$number] = true;
            $collectionId = $this->collections[$abbr]->id;

            $musics = Music::whereHas('collections', function ($query) use ($collectionId, $number) {
                $query->where('collections.id', $collectionId)->where('order_number', $number);
            })->get();

            foreach ($musics as $music) {
                $this->index[$abbr][$number][] = (object) [
                    'music' => $music,
                    'id' => $music->id,
                    'title' => $music->title,
                ];
            }
        }

        return $this->index[$abbr][$number] ?? [];
    }

    /**
     * Index an entry under all of the row's computed order numbers so later rows
     * can match it.
     *
     * @param  array<string, string|null>  $numbers
     */
    private function indexEntry(object $entry, array $numbers): void
    {
        foreach ($numbers as $abbr => $number) {
            if ($number === null) {
                continue;
            }
            $this->queried[$abbr][$number] ??= false;
            foreach ($this->index[$abbr][$number] ?? [] as $existing) {
                if ($existing === $entry) {
                    continue 2;
                }
            }
            $this->index[$abbr][$number][] = $entry;
        }
    }

    /**
     * @param  list<object>  $entries
     * @return list<object>
     */
    private function unique(array $entries): array
    {
        $byId = [];
        foreach ($entries as $entry) {
            $byId[spl_object_id($entry)] = $entry;
        }

        return array_values($byId);
    }

    /**
     * Stable key identifying a (row title, existing song) conflict pair. Songs
     * created during the run have no id, so their normalised title is used.
     */
    private function decisionKey(string $rowTitle, object $entry): string
    {
        $idPart = $entry->id !== null ? 'id:'.$entry->id : 't:'.TitleSimilarity::normalize($entry->title);

        return trim($rowTitle).'||'.$idPart;
    }

    /**
     * Rebuild a decision key from a stored decisions.csv row.
     */
    public static function decisionKeyFor(string $rowTitle, ?string $existingId, string $existingTitle): string
    {
        $idPart = ($existingId !== null && $existingId !== '')
            ? 'id:'.$existingId
            : 't:'.TitleSimilarity::normalize($existingTitle);

        return trim($rowTitle).'||'.$idPart;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function applyRow(Music $music, array $row, array $numbers, int $userId): void
    {
        $this->syncAuthors($music, $row, $userId);
        $this->syncScriptureReferences($music, $row, $userId);
        $this->syncSongbooks($music, $numbers, $userId);
    }

    /**
     * Attach composers/lyricists to the music. An author present in both roles gets
     * a null author_type (the relationship is unique per author/music).
     *
     * @param  array<string, string>  $row
     */
    private function syncAuthors(Music $music, array $row, int $userId): void
    {
        $composers = $this->splitList($row['composers'] ?? '');
        $lyricists = $this->splitList($row['lyricists'] ?? '');

        $roles = [];
        foreach ($composers as $name) {
            $roles[$name] = AuthorType::Composer;
        }
        foreach ($lyricists as $name) {
            $roles[$name] = isset($roles[$name]) ? null : AuthorType::Lyricist;
        }

        foreach ($roles as $name => $role) {
            $author = $this->resolveAuthor($name, $userId);
            $pivot = ['author_type' => $role?->value, 'user_id' => $userId];

            if ($music->authors()->where('authors.id', $author->id)->exists()) {
                $music->authors()->updateExistingPivot($author->id, $pivot);
            } else {
                $music->authors()->attach($author->id, $pivot);
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function syncScriptureReferences(Music $music, array $row, int $userId): void
    {
        foreach ($this->splitList($row['scripture_refs'] ?? '') as $reference) {
            MusicScriptureReference::firstOrCreate(
                ['music_id' => $music->id, 'reference' => $reference],
                [
                    'reference_type' => ScriptureReferenceType::Exact->value,
                    'text' => '',
                    'user_id' => $userId,
                ],
            );
        }
    }

    /**
     * @param  array<string, string|null>  $numbers
     */
    private function syncSongbooks(Music $music, array $numbers, int $userId): void
    {
        foreach ($numbers as $abbr => $number) {
            if ($number === null) {
                continue;
            }
            $collection = $this->collections[$abbr];
            $pivot = ['order_number' => $number, 'user_id' => $userId];

            if ($music->collections()->where('collections.id', $collection->id)->exists()) {
                $music->collections()->updateExistingPivot($collection->id, $pivot);
            } else {
                $music->collections()->attach($collection->id, $pivot);
            }
        }
    }

    /**
     * Compute the order number per songbook abbreviation for a CSV row.
     *
     * @param  array<string, string>  $row
     * @return array<string, string|null>
     */
    private function computeNumbers(array $row): array
    {
        $numbers = [];
        foreach (self::SINGLE_BOOKS as $column => $abbr) {
            $numbers[$abbr] = $this->clean($row[$column] ?? null);
        }
        foreach (self::MERGED_BOOKS as $abbr => [$oldCol, $newCol, $pad]) {
            $numbers[$abbr] = $this->mergedNumber($row[$oldCol] ?? null, $row[$newCol] ?? null, $pad);
        }

        return $numbers;
    }

    /**
     * Candidate order numbers to match an existing song by, per songbook.
     *
     * For merged songbooks the candidates also include the plain régi number,
     * because legacy records are stored under that single number rather than the
     * computed "régi/új" form.
     *
     * @param  array<string, string>  $row
     * @return array<string, list<string>>
     */
    private function matchCandidates(array $row): array
    {
        $candidates = [];

        foreach (self::SINGLE_BOOKS as $column => $abbr) {
            $value = $this->clean($row[$column] ?? null);
            if ($value !== null) {
                $candidates[$abbr] = [$value];
            }
        }

        foreach (self::MERGED_BOOKS as $abbr => [$oldCol, $newCol, $pad]) {
            $list = [];
            $merged = $this->mergedNumber($row[$oldCol] ?? null, $row[$newCol] ?? null, $pad);
            if ($merged !== null) {
                $list[] = $merged;
            }
            $old = $this->clean($row[$oldCol] ?? null);
            if ($old !== null) {
                $list[] = $old;
            }
            if ($list !== []) {
                $candidates[$abbr] = array_values(array_unique($list));
            }
        }

        return $candidates;
    }

    /**
     * Build the order number for a merged (régi/új) songbook.
     */
    private function mergedNumber(?string $old, ?string $new, bool $padNew): ?string
    {
        $old = $this->clean($old);
        $new = $this->clean($new);

        if ($old !== null && $new !== null) {
            if ($old === $new) {
                return $old;
            }

            return $old.'/'.($padNew ? $this->pad3($new) : $new);
        }
        if ($old !== null) {
            return $old.'R';
        }
        if ($new !== null) {
            return $padNew ? $this->pad3($new) : $new;
        }

        return null;
    }

    /**
     * Zero-pad the leading numeric run to three digits (e.g. "2" -> "002").
     */
    private function pad3(string $value): string
    {
        return preg_replace_callback(
            '/^(\d+)/',
            fn (array $m): string => str_pad($m[1], 3, '0', STR_PAD_LEFT),
            $value,
        );
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $value)), fn ($v) => $v !== ''));
    }

    private function resolveAuthor(string $name, int $userId): Author
    {
        return $this->authorCache[$name] ??= Author::firstOrCreate(
            ['name' => $name, 'is_private' => false],
            ['user_id' => $userId],
        );
    }
}
