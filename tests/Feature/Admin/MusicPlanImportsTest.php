<?php

use App\Livewire\Pages\Admin\MusicPlanImports;
use App\Models\Music;
use App\Models\MusicImport;
use App\Models\MusicPlanImport;
use App\Models\MusicPlanImportItem;
use App\Models\SlotImport;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('navigateToMerge redirects to music-merger with correct music IDs', function () {
    $musicA = Music::factory()->create();
    $musicB = Music::factory()->create();

    $import = MusicPlanImport::create(['source_file' => 'test.md']);
    $importItem = MusicPlanImportItem::create([
        'music_plan_import_id' => $import->id,
        'celebration_date' => now(),
        'celebration_info' => 'Márc. 1. VASÁRNAP',
    ]);
    $slotImport = SlotImport::create([
        'music_plan_import_id' => $import->id,
        'name' => 'Ének',
        'column_number' => 1,
    ]);

    $importA = MusicImport::create([
        'music_plan_import_item_id' => $importItem->id,
        'slot_import_id' => $slotImport->id,
        'music_id' => $musicA->id,
        'abbreviation' => 'ÉE267/Ho23',
        'merge_suggestion' => 'ÉE267/Ho23',
    ]);
    MusicImport::create([
        'music_plan_import_item_id' => $importItem->id,
        'slot_import_id' => $slotImport->id,
        'music_id' => $musicB->id,
        'abbreviation' => 'ÉE267/Ho23',
        'merge_suggestion' => 'ÉE267/Ho23',
    ]);

    Livewire::test(MusicPlanImports::class)
        ->set('selectedImportId', $import->id)
        ->call('navigateToMerge', $importA->id)
        ->assertRedirect(route('music-merger', ['left' => $musicA->id, 'right' => $musicB->id]));
});

test('navigateToMerge does nothing when merge suggestion has only one distinct music', function () {
    $import = MusicPlanImport::create(['source_file' => 'test.md']);
    $importItem = MusicPlanImportItem::create([
        'music_plan_import_id' => $import->id,
        'celebration_date' => now(),
        'celebration_info' => 'Márc. 1. VASÁRNAP',
    ]);
    $slotImport = SlotImport::create([
        'music_plan_import_id' => $import->id,
        'name' => 'Ének',
        'column_number' => 1,
    ]);

    $music = Music::factory()->create();
    $importRecord = MusicImport::create([
        'music_plan_import_item_id' => $importItem->id,
        'slot_import_id' => $slotImport->id,
        'music_id' => $music->id,
        'abbreviation' => 'ÉE267/Ho23',
        'merge_suggestion' => 'ÉE267/Ho23',
    ]);

    // Only one distinct music_id — should not redirect
    Livewire::test(MusicPlanImports::class)
        ->set('selectedImportId', $import->id)
        ->call('navigateToMerge', $importRecord->id)
        ->assertNoRedirect();
});

test('navigateToMerge does nothing when no import is selected', function () {
    $import = MusicPlanImport::create(['source_file' => 'test.md']);
    $importItem = MusicPlanImportItem::create([
        'music_plan_import_id' => $import->id,
        'celebration_date' => now(),
        'celebration_info' => 'Márc. 1. VASÁRNAP',
    ]);
    $slotImport = SlotImport::create([
        'music_plan_import_id' => $import->id,
        'name' => 'Ének',
        'column_number' => 1,
    ]);

    $music = Music::factory()->create();
    $importRecord = MusicImport::create([
        'music_plan_import_item_id' => $importItem->id,
        'slot_import_id' => $slotImport->id,
        'music_id' => $music->id,
        'abbreviation' => 'ÉE267/Ho23',
        'merge_suggestion' => 'ÉE267/Ho23',
    ]);

    Livewire::test(MusicPlanImports::class)
        ->call('navigateToMerge', $importRecord->id)
        ->assertNoRedirect();
});
