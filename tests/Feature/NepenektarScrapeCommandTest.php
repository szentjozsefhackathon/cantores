<?php

use App\Models\Music;
use App\Models\MusicUrl;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function makeCsv(array $rows): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'nepenektar_test_');
    $fp = fopen($tmp, 'w');
    fputcsv($fp, ['item_id', 'title', 'subtitle', 'link', 'sources', 'music_id']);
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    return $tmp;
}

it('updates title and subtitle for existing music during import', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create([
        'title' => 'Old Title',
        'subtitle' => 'Old Subtitle',
        'user_id' => $user->id,
    ]);

    $csv = makeCsv([
        [1, 'New Title', 'New Subtitle', 'some/path', 'ÉE1', $music->id],
    ]);

    $this->artisan('cantores:nepenektar-scrape', [
        'action' => 'import',
        '--user' => $user->id,
        '--csv' => $csv,
    ])->assertSuccessful();

    unlink($csv);

    $music->refresh();
    expect($music->title)->toBe('New Title')
        ->and($music->subtitle)->toBe('New Subtitle');
});

it('clears subtitle when csv subtitle is empty for existing music', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create([
        'title' => 'Old Title',
        'subtitle' => 'Had a subtitle',
        'user_id' => $user->id,
    ]);

    $csv = makeCsv([
        [1, 'New Title', '', 'some/path', 'ÉE1', $music->id],
    ]);

    $this->artisan('cantores:nepenektar-scrape', [
        'action' => 'import',
        '--user' => $user->id,
        '--csv' => $csv,
    ])->assertSuccessful();

    unlink($csv);

    $music->refresh();
    expect($music->title)->toBe('New Title')
        ->and($music->subtitle)->toBeNull();
});

it('creates a new music record when music_id is empty', function () {
    $user = User::factory()->create();

    $csv = makeCsv([
        [1, 'Brand New Song', 'A subtitle', 'new/path', '', ''],
    ]);

    $this->artisan('cantores:nepenektar-scrape', [
        'action' => 'import',
        '--user' => $user->id,
        '--csv' => $csv,
    ])->assertSuccessful();

    unlink($csv);

    expect(Music::where('title', 'Brand New Song')->where('subtitle', 'A subtitle')->exists())->toBeTrue();
});

it('creates a music url for an existing music record', function () {
    $user = User::factory()->create();
    $music = Music::factory()->create(['user_id' => $user->id]);

    $csv = makeCsv([
        [1, $music->title, '', 'some/path', '', $music->id],
    ]);

    $this->artisan('cantores:nepenektar-scrape', [
        'action' => 'import',
        '--user' => $user->id,
        '--csv' => $csv,
    ])->assertSuccessful();

    unlink($csv);

    expect(MusicUrl::where('music_id', $music->id)
        ->where('url', 'https://nepenektar.hu/idoszak/some/path')
        ->where('label', 'sheet_music')
        ->exists()
    )->toBeTrue();
});
