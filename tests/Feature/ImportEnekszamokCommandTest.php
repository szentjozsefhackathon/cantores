<?php

use App\AuthorType;
use App\Models\Collection;
use App\Models\Music;
use App\Models\User;
use App\ScriptureReferenceType;

use function Pest\Laravel\artisan;

const ENEKSZAMOK_HEADER = [
    'title', 'original_title', 'composers', 'lyricists', 'scripture_refs',
    'Sárga', 'Zöld', 'Kék Régi', 'Kék Új', 'Barna', 'Téglás Régi', 'Téglás Új',
    'Emmánuel Örvendezzetek', 'Emmánuel Jézus Él', 'DÚR', 'Szent András',
];

const DECISIONS_HEADER = ['row_title', 'existing_id', 'existing_title', 'matched_via', 'similarity', 'decision'];

/**
 * @param  array<int, array<string, string>>  $rows
 */
function writeSongsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'songs').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ENEKSZAMOK_HEADER);
    foreach ($rows as $row) {
        $line = [];
        foreach (ENEKSZAMOK_HEADER as $column) {
            $line[] = $row[$column] ?? '';
        }
        fputcsv($handle, $line);
    }
    fclose($handle);

    return $path;
}

/**
 * @param  array<int, array<string, string>>  $rows
 */
function writeDecisionsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'decisions').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, DECISIONS_HEADER);
    foreach ($rows as $row) {
        $line = [];
        foreach (DECISIONS_HEADER as $column) {
            $line[] = $row[$column] ?? '';
        }
        fputcsv($handle, $line);
    }
    fclose($handle);

    return $path;
}

function createSongbookCollections(): void
{
    foreach (['SK', 'ZK', 'BK', 'JÉL', 'DÚR', 'SZTA', 'KÉK', 'TORG'] as $abbr) {
        Collection::factory()->create(['abbreviation' => $abbr, 'is_private' => false]);
    }
}

function runEnekszamokImport(array $rows, ?string $decisionsPath = null): Illuminate\Testing\PendingCommand
{
    $user = User::factory()->create();
    $path = writeSongsCsv($rows);

    return artisan('cantores:import-enekszamok', [
        '--user' => $user->id,
        '--path' => $path,
        '--decisions' => $decisionsPath ?? sys_get_temp_dir().'/decisions_'.uniqid().'.csv',
    ]);
}

it('creates a song from a csv row', function () {
    createSongbookCollections();

    runEnekszamokImport([
        ['title' => 'Áldjad én lelkem', 'original_title' => 'Lobe den Herren'],
    ])->assertSuccessful();

    $music = Music::where('title', 'Áldjad én lelkem')->first();
    expect($music)->not->toBeNull()
        ->and($music->subtitle)->toBe('Lobe den Herren')
        ->and($music->is_private)->toBeFalse();
});

it('maps composer-only and lyricist-only roles, and both roles to null', function () {
    createSongbookCollections();

    runEnekszamokImport([
        [
            'title' => 'Test Song',
            'composers' => 'Bach, Johann Sebastian|Both Author',
            'lyricists' => 'Lyric Writer|Both Author',
        ],
    ])->assertSuccessful();

    $music = Music::where('title', 'Test Song')->first();
    $roles = $music->authors()->get()->mapWithKeys(
        fn ($author) => [$author->name => $author->pivot->author_type],
    );

    expect($roles['Bach, Johann Sebastian'])->toBe(AuthorType::Composer)
        ->and($roles['Lyric Writer'])->toBe(AuthorType::Lyricist)
        ->and($roles['Both Author'])->toBeNull();
});

it('formats KÉK numbers for both, old-only and new-only', function () {
    createSongbookCollections();

    runEnekszamokImport([
        ['title' => 'Both', 'Kék Régi' => '1', 'Kék Új' => '2'],
        ['title' => 'OldOnly', 'Kék Régi' => '44'],
        ['title' => 'NewOnly', 'Kék Új' => '7'],
    ])->assertSuccessful();

    expect(songbookNumber('Both', 'KÉK'))->toBe('1/002')
        ->and(songbookNumber('OldOnly', 'KÉK'))->toBe('44R')
        ->and(songbookNumber('NewOnly', 'KÉK'))->toBe('007');
});

it('formats TORG numbers without padding the new number', function () {
    createSongbookCollections();

    runEnekszamokImport([
        ['title' => 'Both', 'Téglás Régi' => '123', 'Téglás Új' => '234'],
        ['title' => 'OldOnly', 'Téglás Régi' => '12'],
        ['title' => 'NewOnly', 'Téglás Új' => '9'],
    ])->assertSuccessful();

    expect(songbookNumber('Both', 'TORG'))->toBe('123/234')
        ->and(songbookNumber('OldOnly', 'TORG'))->toBe('12R')
        ->and(songbookNumber('NewOnly', 'TORG'))->toBe('9');
});

it('imports single-column songbook numbers verbatim', function () {
    createSongbookCollections();

    runEnekszamokImport([
        ['title' => 'Single', 'Sárga' => '101', 'DÚR' => '44/B'],
    ])->assertSuccessful();

    expect(songbookNumber('Single', 'SK'))->toBe('101')
        ->and(songbookNumber('Single', 'DÚR'))->toBe('44/B');
});

it('creates exact scripture references with empty text', function () {
    createSongbookCollections();

    runEnekszamokImport([
        ['title' => 'Scripture Song', 'scripture_refs' => 'Zsolt 103|Jn 3,16'],
    ])->assertSuccessful();

    $music = Music::where('title', 'Scripture Song')->first();
    $refs = $music->scriptureReferences()->get();

    expect($refs)->toHaveCount(2)
        ->and($refs->pluck('reference')->all())->toContain('Zsolt 103', 'Jn 3,16')
        ->and($refs->first()->reference_type)->toBe(ScriptureReferenceType::Exact)
        ->and($refs->first()->text)->toBe('');
});

it('uses only the régi number when régi and új are equal', function () {
    createSongbookCollections();

    runEnekszamokImport([
        ['title' => 'SameKek', 'Kék Régi' => '5', 'Kék Új' => '5'],
        ['title' => 'SameTorg', 'Téglás Régi' => '341', 'Téglás Új' => '341'],
    ])->assertSuccessful();

    expect(songbookNumber('SameKek', 'KÉK'))->toBe('5')
        ->and(songbookNumber('SameTorg', 'TORG'))->toBe('341');
});

it('merges into an existing song with a similar title matched by collection number', function () {
    createSongbookCollections();

    $existing = Music::factory()->create(['title' => 'Áldjad én lelkem']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    runEnekszamokImport([
        ['title' => 'Áldjad én lelkem!', 'Sárga' => '5', 'Zöld' => '99'],
    ])->expectsOutputToContain('Updated: 1')
        ->assertSuccessful();

    expect(Music::where('title', 'Áldjad én lelkem!')->exists())->toBeFalse()
        ->and(songbookNumber('Áldjad én lelkem', 'SK'))->toBe('5')
        ->and(songbookNumber('Áldjad én lelkem', 'ZK'))->toBe('99');
});

it('matches a legacy song stored under the plain régi number for a merged book', function () {
    createSongbookCollections();

    $existing = Music::factory()->create(['title' => 'Mennyei király']);
    $kek = Collection::where('abbreviation', 'KÉK')->firstOrFail();
    $existing->collections()->attach($kek->id, ['order_number' => '316', 'user_id' => $existing->user_id]);

    runEnekszamokImport([
        ['title' => 'Mennyei király', 'Kék Régi' => '316'],
    ])->expectsOutputToContain('Updated: 1')
        ->assertSuccessful();

    expect(songbookNumber('Mennyei király', 'KÉK'))->toBe('316R');
});

it('adds every row number to all existing songs it matches', function () {
    createSongbookCollections();
    $dur = Collection::where('abbreviation', 'DÚR')->firstOrFail();
    $zk = Collection::where('abbreviation', 'ZK')->firstOrFail();

    $songA = Music::factory()->create(['title' => 'Közös ének']);
    $songA->collections()->attach($dur->id, ['order_number' => '28', 'user_id' => $songA->user_id]);

    $songB = Music::factory()->create(['title' => 'Közös ének']);
    $songB->collections()->attach($zk->id, ['order_number' => '339', 'user_id' => $songB->user_id]);

    runEnekszamokImport([
        ['title' => 'Közös ének', 'Zöld' => '339', 'DÚR' => '28'],
    ])->expectsOutputToContain('Updated: 1')
        ->assertSuccessful();

    expect(songbookNumberById($songA->id, 'DÚR'))->toBe('28')
        ->and(songbookNumberById($songA->id, 'ZK'))->toBe('339')
        ->and(songbookNumberById($songB->id, 'DÚR'))->toBe('28')
        ->and(songbookNumberById($songB->id, 'ZK'))->toBe('339');
});

it('collects numbers across rows that share a collection number', function () {
    createSongbookCollections();
    $dur = Collection::where('abbreviation', 'DÚR')->firstOrFail();

    $legacy = Music::factory()->create(['title' => 'Halotti ének']);
    $legacy->collections()->attach($dur->id, ['order_number' => '28', 'user_id' => $legacy->user_id]);

    runEnekszamokImport([
        ['title' => 'Halotti ének', 'Zöld' => '339'],
        ['title' => 'Halotti ének', 'Zöld' => '339', 'DÚR' => '28'],
    ])->assertSuccessful();

    $songs = Music::where('title', 'Halotti ének')->get();
    expect($songs)->toHaveCount(2);
    foreach ($songs as $song) {
        expect(songbookNumberById($song->id, 'ZK'))->toBe('339')
            ->and(songbookNumberById($song->id, 'DÚR'))->toBe('28');
    }
});

it('aborts the import when a number match has a too-different title and no decision', function () {
    createSongbookCollections();

    $existing = Music::factory()->create(['title' => 'Alfa']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    runEnekszamokImport([
        ['title' => 'Omega', 'Sárga' => '5', 'Zöld' => '99'],
    ])->expectsOutputToContain('undecided')
        ->assertFailed();

    expect(Music::where('title', 'Omega')->exists())->toBeFalse()
        ->and(songbookNumber('Alfa', 'ZK'))->toBeNull();
});

it('merges a too-different title match when the decision is merge', function () {
    createSongbookCollections();

    $existing = Music::factory()->create(['title' => 'Alfa']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    $decisions = writeDecisionsCsv([
        ['row_title' => 'Omega', 'existing_id' => (string) $existing->id, 'existing_title' => 'Alfa', 'decision' => 'm'],
    ]);

    runEnekszamokImport([
        ['title' => 'Omega', 'Sárga' => '5', 'Zöld' => '99'],
    ], $decisions)->expectsOutputToContain('Updated: 1')
        ->assertSuccessful();

    expect(Music::where('title', 'Omega')->exists())->toBeFalse()
        ->and(songbookNumber('Alfa', 'SK'))->toBe('5')
        ->and(songbookNumber('Alfa', 'ZK'))->toBe('99');
});

it('creates a duplicate when the decision is separate', function () {
    createSongbookCollections();

    $existing = Music::factory()->create(['title' => 'Alfa']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    $decisions = writeDecisionsCsv([
        ['row_title' => 'Omega', 'existing_id' => (string) $existing->id, 'existing_title' => 'Alfa', 'decision' => 'd'],
    ]);

    runEnekszamokImport([
        ['title' => 'Omega', 'Sárga' => '5', 'Zöld' => '99'],
    ], $decisions)->expectsOutputToContain('Duplicates: 1')
        ->assertSuccessful();

    $duplicate = Music::where('title', 'Omega')->first();
    expect($duplicate)->not->toBeNull()
        ->and(songbookNumberById($duplicate->id, 'SK'))->toBe('5')
        ->and(songbookNumberById($duplicate->id, 'ZK'))->toBe('99')
        ->and(songbookNumber('Alfa', 'ZK'))->toBeNull();
});

it('does not match by title when no collection number matches', function () {
    createSongbookCollections();
    Music::factory()->create(['title' => 'Existing Song']);

    runEnekszamokImport([
        ['title' => 'Existing Song', 'Sárga' => '5'],
    ])->expectsOutputToContain('Created: 1')
        ->assertSuccessful();

    expect(Music::where('title', 'Existing Song')->count())->toBe(2);
});

it('fails the preflight when a required collection is missing', function () {
    Collection::factory()->create(['abbreviation' => 'SK', 'is_private' => false]);

    runEnekszamokImport([
        ['title' => 'Whatever', 'Sárga' => '1'],
    ])->assertFailed();

    expect(Music::where('title', 'Whatever')->exists())->toBeFalse();
});

function songbookNumber(string $title, string $abbreviation): ?string
{
    $music = Music::where('title', $title)->firstOrFail();
    $collection = $music->collections()->where('abbreviation', $abbreviation)->first();

    return $collection?->pivot->order_number;
}

function songbookNumberById(int $id, string $abbreviation): ?string
{
    $music = Music::findOrFail($id);
    $collection = $music->collections()->where('abbreviation', $abbreviation)->first();

    return $collection?->pivot->order_number;
}
