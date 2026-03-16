<?php

use App\Enums\DirektoriumProcessingStatus;
use App\Jobs\ProcessDirektoriumJob;
use App\Livewire\Pages\Admin\DirektoriumEditions;
use App\Livewire\Pages\Admin\DirektoriumEntries;
use App\Models\DirektoriumEdition;
use App\Models\DirektoriumEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

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

test('partial cleanup keeps entries updated during current run', function () {
    $startedAt = CarbonImmutable::parse('2026-03-16 10:00:00');
    $runUpdatedAt = $startedAt->addMinute();
    $previousRunAt = $startedAt->subDay();

    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => 'processing',
        'processing_started_at' => $startedAt,
        'is_current' => false,
        'total_pages' => 180,
        'processed_pages' => 88,
    ]);

    $updatedThisRun = DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-03-27',
        'markdown_text' => 'Updated during this run',
        'pdf_page_start' => 87,
        'pdf_page_end' => 87,
        'created_at' => $previousRunAt,
        'updated_at' => $runUpdatedAt,
    ]);

    $staleInRange = DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-03-28',
        'markdown_text' => 'Stale entry in range',
        'pdf_page_start' => 88,
        'pdf_page_end' => 88,
        'created_at' => $previousRunAt,
        'updated_at' => $previousRunAt,
    ]);

    $boundaryPage = DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2025-03-26',
        'markdown_text' => 'Boundary page fragment',
        'pdf_page_start' => 86,
        'pdf_page_end' => 86,
        'created_at' => $previousRunAt,
        'updated_at' => $previousRunAt,
    ]);

    $job = new ProcessDirektoriumJob($edition, 86, 88);
    $method = new ReflectionMethod(ProcessDirektoriumJob::class, 'deleteStaleEntries');
    $method->setAccessible(true);
    $method->invoke($job, 86, 88, 180);

    expect(DirektoriumEntry::query()->find($updatedThisRun->id))->not->toBeNull()
        ->and(DirektoriumEntry::query()->find($staleInRange->id))->toBeNull()
        ->and(DirektoriumEntry::query()->find($boundaryPage->id))->not->toBeNull();
});

test('admin can manually unlock a stale direktorium processing run from the ui', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => DirektoriumProcessingStatus::Processing,
        'processing_started_at' => now()->subHour(),
        'processed_pages' => 42,
        'total_pages' => 180,
        'is_current' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(DirektoriumEditions::class)
        ->call('markAsFailed', $edition->id);

    expect($edition->fresh()->processing_status)->toBe(DirektoriumProcessingStatus::Failed)
        ->and($edition->fresh()->processing_error)->toContain('kézzel leállítottad')
        ->and($edition->fresh()->processing_completed_at)->not->toBeNull();
});

test('admin direktorium process action asks for confirmation in the ui', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => DirektoriumProcessingStatus::Pending,
        'processed_pages' => 0,
        'total_pages' => 180,
        'is_current' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.direktorium'))
        ->assertSuccessful()
        ->assertSee('window.confirm(confirmMessage)', false)
        ->assertSee('Feldolgozás indítása');
});

test('direktorium job failed hook marks stuck processing editions as failed', function () {
    $edition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.pdf',
        'file_path' => 'direktorium/2025/direktorium-2025.pdf',
        'processing_status' => DirektoriumProcessingStatus::Processing,
        'processing_started_at' => now()->subMinutes(10),
        'processed_pages' => 12,
        'total_pages' => 180,
        'is_current' => false,
    ]);

    $job = new ProcessDirektoriumJob($edition);
    $job->failed(new RuntimeException('Queue worker crashed'));

    expect($edition->fresh()->processing_status)->toBe(DirektoriumProcessingStatus::Failed)
        ->and($edition->fresh()->processing_error)->toBe('Queue worker crashed');
});

test('admin can browse direktorium entries in the admin table', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    $edition = DirektoriumEdition::create([
        'year' => 2026,
        'original_filename' => 'direktorium-2026.md',
        'file_path' => 'direktorium/2026/direktorium-2026.md',
        'processing_status' => DirektoriumProcessingStatus::Completed,
        'is_current' => true,
        'total_pages' => 200,
        'processed_pages' => 200,
    ]);

    DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2026-03-19',
        'markdown_text' => '**SZENT JÓZSEF GY1 V0 — Ü**\n*fehér*\nOlv.: Mt 1,16',
        'pdf_page_start' => 77,
        'pdf_page_end' => 77,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.direktorium.entries'))
        ->assertSuccessful()
        ->assertSee('Direktórium bejegyzések')
        ->assertSee('SZENT JÓZSEF')
        ->assertSee('direktorium-2026.md');
});

test('direktorium entries admin table trims long full text previews', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    $edition = DirektoriumEdition::create([
        'year' => 2026,
        'original_filename' => 'direktorium-2026.md',
        'file_path' => 'direktorium/2026/direktorium-2026.md',
        'processing_status' => DirektoriumProcessingStatus::Completed,
        'is_current' => true,
        'total_pages' => 200,
        'processed_pages' => 200,
    ]);

    $fullText = 'Ez egy nagyon hosszu direktoriumi bejegyzes, amelyet a tablazatban csak rovid elonezetkent kell megjeleniteni, hogy a sor ne nyuljon tul hosszan es a teljes szoveg ne foglaljon el tul sok helyet.';
    $trimmedPreview = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($fullText), 100);

    DirektoriumEntry::create([
        'direktorium_edition_id' => $edition->id,
        'entry_date' => '2026-03-20',
        'markdown_text' => $fullText,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.direktorium.entries'))
        ->assertSuccessful()
        ->assertSee($trimmedPreview)
        ->assertDontSee($fullText);
});

test('direktorium entries admin page filters by selected edition', function () {
    $admin = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole($adminRole);

    $olderEdition = DirektoriumEdition::create([
        'year' => 2025,
        'original_filename' => 'direktorium-2025.md',
        'file_path' => 'direktorium/2025/direktorium-2025.md',
        'processing_status' => DirektoriumProcessingStatus::Completed,
        'is_current' => false,
        'total_pages' => 180,
        'processed_pages' => 180,
    ]);

    $currentEdition = DirektoriumEdition::create([
        'year' => 2026,
        'original_filename' => 'direktorium-2026.md',
        'file_path' => 'direktorium/2026/direktorium-2026.md',
        'processing_status' => DirektoriumProcessingStatus::Completed,
        'is_current' => true,
        'total_pages' => 200,
        'processed_pages' => 200,
    ]);

    DirektoriumEntry::create([
        'direktorium_edition_id' => $olderEdition->id,
        'entry_date' => '2025-12-24',
        'markdown_text' => '**OLDER ENTRY**\n*viola*',
    ]);

    DirektoriumEntry::create([
        'direktorium_edition_id' => $currentEdition->id,
        'entry_date' => '2026-01-06',
        'markdown_text' => '**CURRENT ENTRY**\n*fehér*',
    ]);

    Livewire::actingAs($admin)
        ->test(DirektoriumEntries::class)
        ->set('editionFilter', (string) $currentEdition->id)
        ->assertSee('CURRENT ENTRY')
        ->assertDontSee('OLDER ENTRY');
});

test('non admin users cannot access direktorium entries admin page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.direktorium.entries'))
        ->assertForbidden();
});
