<?php

use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicImport;
use App\Models\MusicPlanImport;
use App\Models\SlotImport;

beforeEach(function () {
    $this->tempFiles = [];
})->afterEach(function () {
    foreach ($this->tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

/**
 * Create a temporary markdown table file for testing.
 *
 * @param  array<int, string>  $musicColumns
 * @param  array<int, array<int, string>>  $rows  first cell in each row is the date/info column
 */
function makeMusicPlanMarkdown(array $musicColumns, array $rows): string
{
    $header = '| Dátum | '.implode(' | ', $musicColumns).' |';
    $separator = '| '.implode(' | ', array_fill(0, count($musicColumns) + 1, '---')).' |';

    $lines = [$header, $separator];
    foreach ($rows as $row) {
        $lines[] = '| '.implode(' | ', $row).' |';
    }

    $path = tempnam(sys_get_temp_dir(), 'mpi_test_').'.md';
    file_put_contents($path, implode("\n", $lines));

    return $path;
}

test('unicode abbreviation ÉE267 matches collection and creates import record', function () {
    // Use the pre-seeded ÉE collection (created by TestSeeder)
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '267']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE267']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $musicImport = MusicImport::where('abbreviation', 'ÉE267')->first();
    expect($musicImport)->not->toBeNull();
    expect($musicImport->music_id)->toBe($music->id);
    expect($musicImport->merge_suggestion)->toBeNull();
});

test('Ho abbreviation is expanded to SZVU before lookup', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '23']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ho23']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $musicImport = MusicImport::where('abbreviation', 'Ho23')->first();
    expect($musicImport)->not->toBeNull();
    expect($musicImport->music_id)->toBe($music->id);
});

test('ÉE267/H23 creates import records for both musics with merge suggestion set', function () {
    // Use the pre-seeded ÉE collection
    $eeCollection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $szCollection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    $musicEe = Music::factory()->create();
    $musicSzvu = Music::factory()->create();
    $eeCollection->music()->attach($musicEe->id, ['order_number' => '267']);
    $szCollection->music()->attach($musicSzvu->id, ['order_number' => '23']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE267/Ho23']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $imports = MusicImport::where('merge_suggestion', 'ÉE267/Ho23')->get();
    expect($imports)->toHaveCount(2);
    expect($imports->pluck('music_id')->toArray())->toContain($musicEe->id);
    expect($imports->pluck('music_id')->toArray())->toContain($musicSzvu->id);
});

test('slash-separated abbreviation with no matching music still records merge suggestion', function () {
    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE999/H999']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $musicImport = MusicImport::where('merge_suggestion', 'ÉE999/H999')->first();
    expect($musicImport)->not->toBeNull();
    expect($musicImport->music_id)->toBeNull();
});

test('regular abbreviation without slash has no merge suggestion', function () {
    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE100']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $musicImport = MusicImport::where('abbreviation', 'ÉE100')->first();
    expect($musicImport)->not->toBeNull();
    expect($musicImport->merge_suggestion)->toBeNull();
});

test('slash-separated abbreviation where both entries refer to the same music does not set merge suggestion', function () {
    $eeCollection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $szCollection = Collection::factory()->create(['abbreviation' => 'SZVU']);

    // Both ÉE267 and Ho23 point to the SAME Music record — merge is already done
    $music = Music::factory()->create();
    $eeCollection->music()->attach($music->id, ['order_number' => '267']);
    $szCollection->music()->attach($music->id, ['order_number' => '23']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE267/Ho23']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    // Only one MusicImport should be created (deduplicated), with no merge suggestion
    $imports = MusicImport::where('abbreviation', 'ÉE267/Ho23')->get();
    expect($imports)->toHaveCount(1);
    expect($imports->first()->music_id)->toBe($music->id);
    expect($imports->first()->merge_suggestion)->toBeNull();
});

test('primary abbreviation followed by parenthesised alternatives creates separate low-priority records', function () {
    $mgCollection = Collection::factory()->create(['abbreviation' => 'MG']);
    $eeCollection = Collection::where('abbreviation', 'ÉE')->firstOrFail();

    $musicMg = Music::factory()->create();
    $musicEe591 = Music::factory()->create();
    $musicEe592 = Music::factory()->create();

    $mgCollection->music()->attach($musicMg->id, ['order_number' => '380']);
    $eeCollection->music()->attach($musicEe591->id, ['order_number' => '591']);
    $eeCollection->music()->attach($musicEe592->id, ['order_number' => '592']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG380 (ÉE591 v. ÉE592)']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    // MG380 should be imported without low_priority and without a spurious label
    $mg380 = MusicImport::where('abbreviation', 'MG380')->first();
    expect($mg380)->not->toBeNull();
    expect($mg380->music_id)->toBe($musicMg->id);
    expect($mg380->flags)->toBeNull();
    expect($mg380->label)->toBeNull();

    // ÉE591 should be a separate low_priority record
    $ee591 = MusicImport::where('abbreviation', 'ÉE591')->first();
    expect($ee591)->not->toBeNull();
    expect($ee591->music_id)->toBe($musicEe591->id);
    expect($ee591->flags)->toContain('low_priority');

    // ÉE592 should be a separate low_priority record
    $ee592 = MusicImport::where('abbreviation', 'ÉE592')->first();
    expect($ee592)->not->toBeNull();
    expect($ee592->music_id)->toBe($musicEe592->id);
    expect($ee592->flags)->toContain('low_priority');
});

test('import batch is created correctly', function () {
    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE1']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    expect(MusicPlanImport::count())->toBe(1);
    expect(MusicImport::first()->musicPlanImportItem->musicPlanImport)->not->toBeNull();
});

test('comma-separated abbreviations like MG473, MG387 create separate import records', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    $music1 = Music::factory()->create();
    $music2 = Music::factory()->create();
    $collection->music()->attach($music1->id, ['order_number' => '473']);
    $collection->music()->attach($music2->id, ['order_number' => '387']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG473, MG387']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $mg473 = MusicImport::where('abbreviation', 'MG473')->first();
    expect($mg473)->not->toBeNull();
    expect($mg473->music_id)->toBe($music1->id);

    $mg387 = MusicImport::where('abbreviation', 'MG387')->first();
    expect($mg387)->not->toBeNull();
    expect($mg387->music_id)->toBe($music2->id);
});

test('plain text entries like Gloria and Credo are ignored', function () {
    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Gloria, Credo!']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    expect(MusicImport::count())->toBe(0);
});

test('range ÉE801-805 is expanded into five separate import records', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $musics = Music::factory()->count(5)->create();

    foreach (range(801, 805) as $i => $num) {
        $collection->music()->attach($musics[$i]->id, ['order_number' => (string) $num]);
    }

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE801-805']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    foreach (range(801, 805) as $num) {
        $import = MusicImport::where('abbreviation', "ÉE{$num}")->first();
        expect($import)->not->toBeNull();
        expect($import->music_id)->not->toBeNull();
    }
});

test('Ho233A expanded to SZVU and matched case-insensitively against order_number', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    $music = Music::factory()->create();
    // Store the order number in lowercase in the DB
    $collection->music()->attach($music->id, ['order_number' => '233a']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ho233A']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $musicImport = MusicImport::where('abbreviation', 'Ho233A')->first();
    expect($musicImport)->not->toBeNull();
    expect($musicImport->music_id)->toBe($music->id);
});

test('slot-prefix range like "Körmenet: ÉE801-805" expands into five records under the named slot', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $musics = Music::factory()->count(5)->create();

    foreach (range(801, 805) as $i => $num) {
        $collection->music()->attach($musics[$i]->id, ['order_number' => (string) $num]);
    }

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Körmenet: ÉE801-805']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    foreach (range(801, 805) as $num) {
        $import = MusicImport::where('abbreviation', "ÉE{$num}")->first();
        expect($import)->not->toBeNull("Expected MusicImport for ÉE{$num} to exist");
        expect($import->music_id)->not->toBeNull("Expected ÉE{$num} to resolve to a Music record");
    }
});

// ── Second-pass cleaning tests ──────────────────────────────────────────────

test('second pass: "v. ÉE533" is cleaned to "ÉE533" and matched', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '533']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'v. ÉE533']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE533')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
});

test('second pass: "Ad lib. ÉE231 seq" is cleaned to "ÉE231" and matched', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '231']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ad lib. ÉE231 seq']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE231')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
});

test('second pass: "esetleg Ho79" is cleaned to "Ho79" with low_priority flag', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '79']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'esetleg Ho79']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'Ho79')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
    expect($import->flags)->toContain('low_priority');
});

test('second pass: "MG155. Olajszentelésre: MG156" creates two records, second under named slot', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    $music155 = Music::factory()->create();
    $music156 = Music::factory()->create();
    $collection->music()->attach($music155->id, ['order_number' => '155']);
    $collection->music()->attach($music156->id, ['order_number' => '156']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG155. Olajszentelésre: MG156']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import155 = MusicImport::where('abbreviation', 'MG155')->first();
    expect($import155)->not->toBeNull();
    expect($import155->music_id)->toBe($music155->id);

    $import156 = MusicImport::where('abbreviation', 'MG156')->first();
    expect($import156)->not->toBeNull();
    expect($import156->music_id)->toBe($music156->id);

    $slot = SlotImport::where('name', 'Olajszentelésre')->first();
    expect($slot)->not->toBeNull();
    expect($import156->slot_import_id)->toBe($slot->id);
});

test('second pass: "Búcsúbeszéd (ad lib.): ÉE812" is cleaned to "ÉE812" under slot "Búcsúbeszéd"', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '812']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Búcsúbeszéd (ad lib.): ÉE812']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE812')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);

    $slot = SlotImport::where('name', 'Búcsúbeszéd')->first();
    expect($slot)->not->toBeNull();
    expect($import->slot_import_id)->toBe($slot->id);
});

test('second pass: "(Ad lib.: ÉE540)" is cleaned to "ÉE540" with low_priority', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '540']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', '(Ad lib.: ÉE540)']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE540')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
    expect($import->flags)->toContain('low_priority');
});

test('second pass: "Tűzszentelés előtt: ÉE826" creates record under named slot', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '826']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Tűzszentelés előtt: ÉE826']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE826')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);

    $slot = SlotImport::where('name', 'Tűzszentelés előtt')->first();
    expect($slot)->not->toBeNull();
    expect($import->slot_import_id)->toBe($slot->id);
});

test('second pass: "Ad. lib. Húsvéti misztériumjáték ÉE482" yields "ÉE482" under named slot', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '482']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ad. lib. Húsvéti misztériumjáték ÉE482']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE482')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);

    $slot = SlotImport::where('name', 'Húsvéti misztériumjáték')->first();
    expect($slot)->not->toBeNull();
    expect($import->slot_import_id)->toBe($slot->id);
});

test('second pass: "- (Ad lib.: ÉE540)" strips leading dash then handles as low-priority', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '540']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', '- (Ad lib.: ÉE540)']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE540')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
    expect($import->flags)->toContain('low_priority');
});

test('second pass: "seq. ÉE689" strips seq. prefix and matches', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '689']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'seq. ÉE689']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE689')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
});

test('second pass: "Ad lib.: ÉE824 seq" strips both prefix and trailing seq', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '824']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ad lib.: ÉE824 seq']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE824')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
});

test('second pass: "ÉE 413 Kyrie" normalises space in abbreviation and matches', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '413']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE 413 Kyrie']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE413')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
});

test('second pass: "v. MG 359" strips v. and normalises space', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '359']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'v. MG 359']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'MG359')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
});

test('second pass: "v. MG394- 395" normalises range and expands into two records', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    [$m394, $m395] = Music::factory()->count(2)->create()->all();
    $collection->music()->attach($m394->id, ['order_number' => '394']);
    $collection->music()->attach($m395->id, ['order_number' => '395']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'v. MG394- 395']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    foreach (['MG394', 'MG395'] as $abbr) {
        $import = MusicImport::where('abbreviation', $abbr)->first();
        expect($import)->not->toBeNull("Expected MusicImport for {$abbr}");
        expect($import->music_id)->not->toBeNull("Expected {$abbr} to match a Music record");
    }
});

test('second pass: "Ho433- Ho435" normalises repeated-prefix range and expands into three records', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    $musics = Music::factory()->count(3)->create();
    foreach ([433, 434, 435] as $i => $n) {
        $collection->music()->attach($musics[$i]->id, ['order_number' => (string) $n]);
    }

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ho433- Ho435']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    foreach ([433, 434, 435] as $num) {
        // Ho prefix expands to SZVU via ABBREVIATION_MAP, but abbreviation stored as Ho{n}
        $import = MusicImport::where('abbreviation', "Ho{$num}")->first();
        expect($import)->not->toBeNull("Expected MusicImport for Ho{$num}");
        expect($import->music_id)->not->toBeNull("Expected Ho{$num} to match a Music record");
    }
});

test('second pass: "MG220-221 (ÉE557)" creates range records and low-priority alternative', function () {
    $mgCollection = Collection::factory()->create(['abbreviation' => 'MG']);
    $eeCollection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    [$mg220, $mg221] = Music::factory()->count(2)->create()->all();
    $ee557 = Music::factory()->create();
    $mgCollection->music()->attach($mg220->id, ['order_number' => '220']);
    $mgCollection->music()->attach($mg221->id, ['order_number' => '221']);
    $eeCollection->music()->attach($ee557->id, ['order_number' => '557']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG220-221 (ÉE557)']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    foreach (['MG220', 'MG221'] as $abbr) {
        $import = MusicImport::where('abbreviation', $abbr)->first();
        expect($import)->not->toBeNull("Expected MusicImport for {$abbr}");
        expect($import->music_id)->not->toBeNull();
    }

    $altImport = MusicImport::where('abbreviation', 'ÉE557')->first();
    expect($altImport)->not->toBeNull();
    expect($altImport->music_id)->toBe($ee557->id);
    expect($altImport->flags)->toContain('low_priority');
});

test('second pass: "(ÉE538) ÉE414 Kyrie" creates main record and low-priority alternative', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    [$ee414, $ee538] = Music::factory()->count(2)->create()->all();
    $collection->music()->attach($ee414->id, ['order_number' => '414']);
    $collection->music()->attach($ee538->id, ['order_number' => '538']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', '(ÉE538) ÉE414 Kyrie']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $main = MusicImport::where('abbreviation', 'ÉE414')->first();
    expect($main)->not->toBeNull();
    expect($main->music_id)->toBe($ee414->id);
    expect($main->flags)->toBeNull();

    $alt = MusicImport::where('abbreviation', 'ÉE538')->first();
    expect($alt)->not->toBeNull();
    expect($alt->music_id)->toBe($ee538->id);
    expect($alt->flags)->toContain('low_priority');
});

test('second pass: "(ÉE646) napján" yields ÉE646 with low_priority', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '646']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', '(ÉE646) napján']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import = MusicImport::where('abbreviation', 'ÉE646')->first();
    expect($import)->not->toBeNull();
    expect($import->music_id)->toBe($music->id);
    expect($import->flags)->toContain('low_priority');
});

test('second pass: "Vig.: MG256-257" creates two records under slot "Vig."', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    [$mg256, $mg257] = Music::factory()->count(2)->create()->all();
    $collection->music()->attach($mg256->id, ['order_number' => '256']);
    $collection->music()->attach($mg257->id, ['order_number' => '257']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Vig.: MG256-257']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $slot = SlotImport::where('name', 'Vig.')->first();
    expect($slot)->not->toBeNull();

    foreach (['MG256', 'MG257'] as $abbr) {
        $import = MusicImport::where('abbreviation', $abbr)->first();
        expect($import)->not->toBeNull("Expected MusicImport for {$abbr}");
        expect($import->slot_import_id)->toBe($slot->id);
    }
});

test('second pass: standalone range "ÉE819-822" is expanded into four records', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    $musics = Music::factory()->count(4)->create();
    foreach (range(819, 822) as $i => $num) {
        $collection->music()->attach($musics[$i]->id, ['order_number' => (string) $num]);
    }

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE819-822']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    foreach (range(819, 822) as $num) {
        $import = MusicImport::where('abbreviation', "ÉE{$num}")->first();
        expect($import)->not->toBeNull("Expected MusicImport for ÉE{$num}");
        expect($import->music_id)->not->toBeNull("Expected ÉE{$num} to match a Music record");
    }
});

// ── Leading-abbreviation normalisation tests ────────────────────────────────

test('bare number after comma inherits leading abbreviation: "MG478, 485" → MG478 + MG485', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    [$mg478, $mg485] = Music::factory()->count(2)->create()->all();
    $collection->music()->attach($mg478->id, ['order_number' => '478']);
    $collection->music()->attach($mg485->id, ['order_number' => '485']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG478, 485']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $import478 = MusicImport::where('abbreviation', 'MG478')->first();
    expect($import478)->not->toBeNull();
    expect($import478->music_id)->toBe($mg478->id);

    $import485 = MusicImport::where('abbreviation', 'MG485')->first();
    expect($import485)->not->toBeNull();
    expect($import485->music_id)->toBe($mg485->id);
});

test('bare number after comma does not override subsequent explicit abbreviation: "MG478, 485; ÉE658"', function () {
    $mgCollection = Collection::factory()->create(['abbreviation' => 'MG']);
    $eeCollection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    [$mg478, $mg485] = Music::factory()->count(2)->create()->all();
    $ee658 = Music::factory()->create();
    $mgCollection->music()->attach($mg478->id, ['order_number' => '478']);
    $mgCollection->music()->attach($mg485->id, ['order_number' => '485']);
    $eeCollection->music()->attach($ee658->id, ['order_number' => '658']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG478, 485; ÉE658']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    expect(MusicImport::where('abbreviation', 'MG478')->first())->not->toBeNull();
    expect(MusicImport::where('abbreviation', 'MG485')->first())->not->toBeNull();
    expect(MusicImport::where('abbreviation', 'ÉE658')->first()?->music_id)->toBe($ee658->id);

    // Ensure 658 was NOT misinterpreted as MG658
    expect(MusicImport::where('abbreviation', 'MG658')->first())->toBeNull();
});

test('bare number after comma within range context: "MG478-480, 485" → MG478-MG480 + MG485', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'MG']);
    $musics = Music::factory()->count(4)->create();
    foreach ([478, 479, 480, 485] as $i => $num) {
        $collection->music()->attach($musics[$i]->id, ['order_number' => (string) $num]);
    }

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'MG478-480, 485']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    foreach ([478, 479, 480, 485] as $num) {
        $import = MusicImport::where('abbreviation', "MG{$num}")->first();
        expect($import)->not->toBeNull("Expected MusicImport for MG{$num}");
        expect($import->music_id)->not->toBeNull("Expected MG{$num} to match a Music record");
    }
});

test('third pass: "ÉE569" matches both "569 latin" and "569 magyar" variants in the DB', function () {
    $collection = Collection::where('abbreviation', 'ÉE')->firstOrFail();
    [$latin, $magyar] = Music::factory()->count(2)->create()->all();
    $collection->music()->attach($latin->id, ['order_number' => '569 latin']);
    $collection->music()->attach($magyar->id, ['order_number' => '569 magyar']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE569']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $imports = MusicImport::where('abbreviation', 'ÉE569')->orderBy('id')->get();
    expect($imports)->toHaveCount(2);
    expect($imports->pluck('music_id')->sort()->values()->all())
        ->toEqual(collect([$latin->id, $magyar->id])->sort()->values()->all());
    expect($imports->every(fn ($i) => $i->music_id !== null))->toBeTrue();
});

test('third pass: "Ho184" matches both "184A" and "184B" SZVU variants in the DB', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    [$a, $b] = Music::factory()->count(2)->create()->all();
    $collection->music()->attach($a->id, ['order_number' => '184A']);
    $collection->music()->attach($b->id, ['order_number' => '184B']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'Ho184']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])->assertSuccessful();

    $imports = MusicImport::where('abbreviation', 'Ho184')->orderBy('id')->get();
    expect($imports)->toHaveCount(2);
    expect($imports->pluck('music_id')->sort()->values()->all())
        ->toEqual(collect([$a->id, $b->id])->sort()->values()->all());
    expect($imports->every(fn ($i) => $i->music_id !== null))->toBeTrue();
});
