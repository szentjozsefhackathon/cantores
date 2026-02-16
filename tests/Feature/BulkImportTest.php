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
