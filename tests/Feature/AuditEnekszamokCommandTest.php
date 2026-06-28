<?php

use App\Models\Collection;
use App\Models\Music;

use function Pest\Laravel\artisan;

function runEnekszamokAudit(array $rows, string $decisionsPath): Illuminate\Testing\PendingCommand
{
    $path = writeSongsCsv($rows);

    return artisan('cantores:audit-enekszamok', [
        '--path' => $path,
        '--decisions' => $decisionsPath,
    ]);
}

/**
 * @return array<int, array<string, string>>
 */
function readDecisionsCsv(string $path): array
{
    $handle = fopen($path, 'r');
    $header = fgetcsv($handle);
    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = array_combine($header, $data);
    }
    fclose($handle);

    return $rows;
}

it('writes an undecided conflict to decisions.csv', function () {
    createSongbookCollections();
    $existing = Music::factory()->create(['title' => 'Alfa']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    $decisionsPath = sys_get_temp_dir().'/decisions_'.uniqid().'.csv';

    runEnekszamokAudit([
        ['title' => 'Omega', 'Sárga' => '5'],
    ], $decisionsPath)->expectsOutputToContain('1 undecided')
        ->assertSuccessful();

    $rows = readDecisionsCsv($decisionsPath);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['row_title'])->toBe('Omega')
        ->and($rows[0]['existing_id'])->toBe((string) $existing->id)
        ->and($rows[0]['existing_title'])->toBe('Alfa')
        ->and($rows[0]['matched_via'])->toBe('SK 5')
        ->and($rows[0]['decision'])->toBe('');
});

it('reports no conflicts when the matched title is similar', function () {
    createSongbookCollections();
    $existing = Music::factory()->create(['title' => 'Áldjad én lelkem']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    $decisionsPath = sys_get_temp_dir().'/decisions_'.uniqid().'.csv';

    runEnekszamokAudit([
        ['title' => 'Áldjad én lelkem!', 'Sárga' => '5'],
    ], $decisionsPath)->expectsOutputToContain('0 undecided')
        ->assertSuccessful();

    expect(readDecisionsCsv($decisionsPath))->toHaveCount(0);
});

it('preserves an existing decision and reports it as decided', function () {
    createSongbookCollections();
    $existing = Music::factory()->create(['title' => 'Alfa']);
    $sk = Collection::where('abbreviation', 'SK')->firstOrFail();
    $existing->collections()->attach($sk->id, ['order_number' => '5', 'user_id' => $existing->user_id]);

    $decisionsPath = writeDecisionsCsv([
        ['row_title' => 'Omega', 'existing_id' => (string) $existing->id, 'existing_title' => 'Alfa', 'decision' => 'm'],
    ]);

    runEnekszamokAudit([
        ['title' => 'Omega', 'Sárga' => '5'],
    ], $decisionsPath)->expectsOutputToContain('0 undecided')
        ->assertSuccessful();

    $rows = readDecisionsCsv($decisionsPath);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['decision'])->toBe('m');
});
