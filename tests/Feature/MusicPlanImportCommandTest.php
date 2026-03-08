<?php

use App\Models\Collection;
use App\Models\Music;
use App\Models\MusicImport;
use App\Models\MusicPlanImport;

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

test('H abbreviation is expanded to SZVU before lookup', function () {
    $collection = Collection::factory()->create(['abbreviation' => 'SZVU']);
    $music = Music::factory()->create();
    $collection->music()->attach($music->id, ['order_number' => '23']);

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'H23']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $musicImport = MusicImport::where('abbreviation', 'H23')->first();
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

    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE267/H23']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    $imports = MusicImport::where('merge_suggestion', 'ÉE267/H23')->get();
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

test('import batch is created correctly', function () {
    $file = makeMusicPlanMarkdown(['Ének'], [['Márc. 1. VASÁRNAP', 'ÉE1']]);
    $this->tempFiles[] = $file;

    $this->artisan('cantores:musicplan-import', ['file' => $file])
        ->assertSuccessful();

    expect(MusicPlanImport::count())->toBe(1);
    expect(MusicImport::first()->musicPlanImportItem->musicPlanImport)->not->toBeNull();
});
