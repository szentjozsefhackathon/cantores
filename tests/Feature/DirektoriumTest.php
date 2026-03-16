<?php

use App\Jobs\ProcessDirektoriumJob;
use App\Models\DirektoriumEdition;
use App\Models\DirektoriumEntry;
use Illuminate\Support\Facades\Storage;

test('direktorium entry stores and retrieves structured data correctly', function () {
    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'completed',
        'is_current' => true,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    $entry = DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-11-30',
        'entry_key' => 0,
        'celebration_name' => 'Advent I. vasárnapja',
        'rank' => 'főünnep',
        'liturgical_color' => 'viola',
        'funeral_mass_code' => 'GY0',
        'votive_mass_code' => 'V0',
        'is_pro_populo' => true,
        'penitential_level' => 0,
        'has_gloria' => false,
        'has_credo' => true,
        'preface' => 'I. adventi pref.',
        'readings' => [
            ['first_reading' => 'Iz 2,1–5', 'second_reading' => 'Róm 13,11–14', 'gospel' => 'Mt 24,37–44'],
        ],
        'office' => ['main' => 'az adventi vasárnapról', 'lectio' => 'Te Deum'],
        'pdf_page_start' => 30,
    ]);

    expect($entry->celebration_name)->toBe('Advent I. vasárnapja')
        ->and($entry->rank)->toBe('főünnep')
        ->and($entry->has_gloria)->toBeFalse()
        ->and($entry->has_credo)->toBeTrue()
        ->and($entry->is_pro_populo)->toBeTrue()
        ->and($entry->readings[0]['gospel'])->toBe('Mt 24,37–44')
        ->and($entry->office['lectio'])->toBe('Te Deum');
});

test('direktorium entry supports multiple alternatives per day', function () {
    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'completed',
        'is_current' => true,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-12-01',
        'entry_key' => 0,
        'celebration_name' => 'Köznap',
        'rank' => 'köznap',
        'liturgical_color' => 'viola',
    ]);

    DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-12-01',
        'entry_key' => 1,
        'celebration_name' => 'Rorate-mise',
        'rank' => 'köznap',
        'liturgical_color' => 'fehér',
    ]);

    $entries = DirektoriumEntry::forDate('2025-12-01')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->entry_key)->toBe(0)
        ->and($entries->last()->entry_key)->toBe(1);
});

test('direktorium entry can be omitted with transferred date', function () {
    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'completed',
        'is_current' => false,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    $entry = DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-11-30',
        'entry_key' => 2,
        'celebration_name' => 'Szent András apostol',
        'rank' => 'ünnep',
        'liturgical_color' => 'piros',
        'is_omitted' => true,
        'transferred_to_date' => '2025-12-01',
    ]);

    expect($entry->is_omitted)->toBeTrue()
        ->and($entry->transferred_to_date->format('Y-m-d'))->toBe('2025-12-01');
});

test('pdf page route requires authentication', function () {
    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'completed',
        'is_current' => true,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    $response = $this->get(route('direktorium.page', ['edition' => $edition->id, 'page' => 1]));

    $response->assertRedirect('/login');
});

test('edition progress percent calculates correctly', function () {
    $edition = new DirektoriumEdition;
    $edition->total_pages = 180;
    $edition->processed_pages = 90;

    expect($edition->processingProgressPercent())->toBe(50);
});

test('mark as current deactivates other editions', function () {
    $edition1 = DirektoriumEdition::create([
        'year' => 2024,
        'original_filename' => 'direktorium-2024.pdf',
        'file_path' => 'direktorium/2024/direktorium-2024.pdf',
        'processing_status' => 'completed',
        'is_current' => true,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    $edition2 = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'completed',
        'is_current' => false,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    $edition2->markAsCurrent();

    expect($edition1->fresh()->is_current)->toBeFalse()
        ->and($edition2->fresh()->is_current)->toBeTrue();
});

test('process direktorium job saves anthropic prompt to debug storage', function () {
    Storage::fake('local');

    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'processing',
        'is_current' => false,
        'total_pages' => 180,
        'processed_pages' => 10,
    ]);

    $job = new ProcessDirektoriumJob($edition);
    $method = new ReflectionMethod(ProcessDirektoriumJob::class, 'savePromptDebug');
    $method->setAccessible(true);

    $path = $method->invoke($job, 'test anthropic prompt', 3, 4);

    Storage::disk('local')->assertExists($path);

    expect($path)->toBe("direktorium/debug/edition-{$edition->id}/batch-3-4-prompt.txt")
        ->and(Storage::disk('local')->get($path))->toBe('test anthropic prompt');
});
