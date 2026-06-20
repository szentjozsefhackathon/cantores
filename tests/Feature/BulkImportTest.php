<?php

use App\Models\BulkImport;

test('can create bulk import record', function () {
    $record = BulkImport::create([
        'collection' => 'szvu',
        'piece' => 'A kereszténységben hisszük, valljuk régen',
        'reference' => '1',
    ]);

    expect($record->collection)->toBe('szvu');
    expect($record->piece)->toBe('A kereszténységben hisszük, valljuk régen');
    expect($record->reference)->toBe('1');
});

test('reference is cast to string', function () {
    $record = BulkImport::create([
        'collection' => 'test',
        'piece' => 'Test piece',
        'reference' => '5',
    ]);

    expect($record->reference)->toBeString();
    expect($record->reference)->toBe('5');
});

test('can delete records by collection', function () {
    BulkImport::factory()->create(['collection' => 'szvu']);
    BulkImport::factory()->create(['collection' => 'other']);

    expect(BulkImport::where('collection', 'szvu')->count())->toBe(1);
    expect(BulkImport::where('collection', 'other')->count())->toBe(1);

    BulkImport::where('collection', 'szvu')->delete();

    expect(BulkImport::where('collection', 'szvu')->count())->toBe(0);
    expect(BulkImport::where('collection', 'other')->count())->toBe(1);
});

test('mass assignment protection', function () {
    $record = new BulkImport([
        'collection' => 'szvu',
        'piece' => 'Piece',
        'reference' => '1',
        'unknown_field' => 'should not be set',
    ]);

    expect($record->collection)->toBe('szvu');
    expect($record->piece)->toBe('Piece');
    expect($record->reference)->toBe('1');
    expect($record->unknown_field)->toBeNull();
});

test('batch number is required and defaults to 1', function () {
    $record = BulkImport::create([
        'collection' => 'test',
        'piece' => 'Test piece',
        'reference' => '2',
        // batch_number not provided, should default to 1
    ]);

    expect($record->batch_number)->toBe(1);
});

test('batch number is cast to integer', function () {
    $record = BulkImport::create([
        'collection' => 'test',
        'piece' => 'Test piece',
        'reference' => '3',
        'batch_number' => '5',
    ]);

    expect($record->batch_number)->toBeInt();
    expect($record->batch_number)->toBe(5);
});

test('batch number is mass assignable', function () {
    $record = new BulkImport([
        'collection' => 'test',
        'piece' => 'Test piece',
        'reference' => '4',
        'batch_number' => 7,
    ]);

    expect($record->batch_number)->toBe(7);
});

test('importing related does not create a self-referential relation for already-merged music', function () {
    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = App\Models\User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $ee = App\Models\Collection::factory()->create(['abbreviation' => 'TEE']);
    $szvu = App\Models\Collection::factory()->create(['abbreviation' => 'TSZ']);

    // A single, already-merged piece attached to BOTH collections.
    $music = App\Models\Music::factory()->create(['title' => 'A szép Szűz Mária']);
    $music->collections()->attach($ee->id, ['order_number' => '99947']);
    $music->collections()->attach($szvu->id, ['order_number' => '99919']);

    BulkImport::create([
        'collection' => 'ee',
        'piece' => 'A szép Szűz Mária',
        'reference' => '99947',
        'related' => 'TSZ 99919',
        'batch_number' => 99,
    ]);

    Livewire\Livewire::test(App\Livewire\Pages\Admin\BulkImports::class)
        ->set('selectedBatchNumber', 99)
        ->set('selectedCollectionId', $ee->id)
        ->call('importMusic');

    expect(App\Models\MusicRelation::whereColumn('music_id', 'related_music_id')->count())->toBe(0);
    expect(App\Models\MusicRelation::where('music_id', $music->id)->count())->toBe(0);
});

test('importing related links two distinct pieces as duplicates', function () {
    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = App\Models\User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $ee = App\Models\Collection::factory()->create(['abbreviation' => 'TEE']);
    $szvu = App\Models\Collection::factory()->create(['abbreviation' => 'TSZ']);

    $eeMusic = App\Models\Music::factory()->create();
    $eeMusic->collections()->attach($ee->id, ['order_number' => '99100']);

    $szvuMusic = App\Models\Music::factory()->create();
    $szvuMusic->collections()->attach($szvu->id, ['order_number' => '99050']);

    BulkImport::create([
        'collection' => 'ee',
        'piece' => $eeMusic->title,
        'reference' => '99100',
        'related' => 'TSZ 99050',
        'batch_number' => 100,
    ]);

    Livewire\Livewire::test(App\Livewire\Pages\Admin\BulkImports::class)
        ->set('selectedBatchNumber', 100)
        ->set('selectedCollectionId', $ee->id)
        ->call('importMusic');

    $relation = App\Models\MusicRelation::between($eeMusic->id, $szvuMusic->id)->first();
    expect($relation)->not->toBeNull();
    expect($relation->relationship_type)->toBe(App\MusicRelationshipType::Duplicate->value);
});
