<?php

use App\Models\Author;
use App\Models\User;

use function Pest\Laravel\artisan;

function writeAuthorsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'authors').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ['name', 'is_hungarian', 'song_count']);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}

it('creates public authors from the csv', function () {
    $user = User::factory()->create();
    $path = writeAuthorsCsv([
        ['Bach, Johann Sebastian', '0', '3'],
        ['Kodály Zoltán', '1', '5'],
    ]);

    artisan('cantores:import-enekszamok-authors', ['--user' => $user->id, '--path' => $path])
        ->assertSuccessful();

    expect(Author::where('name', 'Bach, Johann Sebastian')->where('is_private', false)->exists())->toBeTrue()
        ->and(Author::where('name', 'Kodály Zoltán')->where('is_private', false)->exists())->toBeTrue();
});

it('skips authors that already exist', function () {
    $user = User::factory()->create();
    Author::factory()->create(['name' => 'Kodály Zoltán', 'is_private' => false]);

    $path = writeAuthorsCsv([
        ['Kodály Zoltán', '1', '5'],
    ]);

    artisan('cantores:import-enekszamok-authors', ['--user' => $user->id, '--path' => $path])
        ->expectsOutputToContain('Skipped (already existed): 1')
        ->assertSuccessful();

    expect(Author::where('name', 'Kodály Zoltán')->count())->toBe(1);
});

it('fails when the user is not found', function () {
    $path = writeAuthorsCsv([['Kodály Zoltán', '1', '5']]);

    artisan('cantores:import-enekszamok-authors', ['--user' => 'nobody@example.com', '--path' => $path])
        ->assertFailed();
});
