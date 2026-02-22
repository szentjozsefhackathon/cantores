<?php

use App\Models\BulkImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('dtx convert command processes file and creates bulk import records', function () {
    // Create a minimal DTX file content
    $dtxContent = <<<'DTX'
;Test collection
RTest
S1

>1
/1 First verse
 A test song line.
/2 Second verse
 Another line.

>2
/1 Another song
 Second song line.
DTX;

    $collection = 'test_'.uniqid();
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    // Mock HTTP response
    Http::fake([
        $url => Http::response($dtxContent, 200),
    ]);

    // Ensure no existing records for this collection
    BulkImport::where('collection', $collection)->delete();

    // Run the command
    $this->artisan('dtx:convert', ['collection' => $collection])
        ->expectsOutput("Downloading DTX file from: {$url}")
        ->expectsOutput('DTX file saved to: '.storage_path("app/private/dtximport/{$collection}.dtx"))
        ->expectsOutput('Stored 2 songs in bulk_imports table for collection \''.$collection.'\'.')
        ->expectsOutput('CSV file created: '.storage_path("app/private/dtximport/{$collection}.csv"))
        ->expectsOutput('Conversion completed.')
        ->expectsOutput('Temporary DTX file removed.')
        ->assertSuccessful();

    // Verify records in database
    $records = BulkImport::where('collection', $collection)->orderBy('reference')->get();
    expect($records)->toHaveCount(2);

    $first = $records[0];
    expect($first->piece)->toBe('A test song line');
    expect($first->reference)->toBe('1');

    $second = $records[1];
    expect($second->piece)->toBe('Second song line');
    expect($second->reference)->toBe('2');

    // Verify CSV file exists
    $csvPath = storage_path("app/private/dtximport/{$collection}.csv");
    expect(file_exists($csvPath))->toBeTrue();

    // Clean up CSV
    unlink($csvPath);
});

test('dtx convert command deletes previous records for same collection', function () {
    $collection = 'test2_'.uniqid();
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    // Create existing record for this collection
    BulkImport::factory()->create(['collection' => $collection, 'reference' => '99']);

    // DTX content with one song
    $dtxContent = <<<'DTX'
;Test collection
RTest2
S1

>1
/1 Only one
 Only song line.
DTX;

    // Mock HTTP response
    Http::fake([
        $url => Http::response($dtxContent, 200),
    ]);

    // Run the command
    $this->artisan('dtx:convert', ['collection' => $collection])->assertSuccessful();

    // Should have only the new record, old one deleted
    $records = BulkImport::where('collection', $collection)->get();
    expect($records)->toHaveCount(1);
    expect($records[0]->piece)->toBe('Only song line');
    expect($records[0]->reference)->toBe('1');

    // Clean up CSV
    $csvPath = storage_path("app/private/dtximport/{$collection}.csv");
    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
});

test('dtx convert command with --title flag uses ienek as title and leaves reference empty', function () {
    $dtxContent = <<<'DTX'
;Test collection
RTest
S1

>1
/1 First verse
 A test song line.
/2 Second verse
 Another line.

>2
/1 Another song
 Second song line.
DTX;

    $collection = 'test_title_'.uniqid();
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    Http::fake([
        $url => Http::response($dtxContent, 200),
    ]);

    BulkImport::where('collection', $collection)->delete();

    $this->artisan('dtx:convert', ['collection' => $collection, '--title' => true])
        ->assertSuccessful();

    $records = BulkImport::where('collection', $collection)->orderBy('piece')->get();
    expect($records)->toHaveCount(2);

    $first = $records[0];
    expect($first->piece)->toBe('1');
    expect($first->reference)->toBe('');

    $second = $records[1];
    expect($second->piece)->toBe('2');
    expect($second->reference)->toBe('');

    // Verify CSV file exists and check its content
    $csvPath = storage_path("app/private/dtximport/{$collection}.csv");
    expect(file_exists($csvPath))->toBeTrue();

    $csvLines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    expect($csvLines)->toHaveCount(3); // header + 2 rows
    // New CSV format: title,reference,"page number",tag
    // fputcsv quotes fields with spaces, and empty fields are not quoted
    expect($csvLines[0])->toBe('title,reference,"page number",tag');
    expect($csvLines[1])->toBe('1,,,');
    expect($csvLines[2])->toBe('2,,,');

    // Clean up CSV
    unlink($csvPath);
});

test('dtx convert command handles missing file', function () {
    $collection = 'nonexistent';
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    // Mock HTTP 404 response
    Http::fake([
        $url => Http::response('Not Found', 404),
    ]);

    $this->artisan('dtx:convert', ['collection' => $collection])
        ->expectsOutput("Downloading DTX file from: {$url}")
        ->expectsOutput('HTTP error: 404 - Not Found')
        ->assertFailed();
});

test('dtx convert command handles empty songs', function () {
    $collection = 'empty_'.uniqid();
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    // DTX with no songs
    $dtxContent = <<<'DTX'
;Empty
REmpty
S1
DTX;

    // Mock HTTP response
    Http::fake([
        $url => Http::response($dtxContent, 200),
    ]);

    $this->artisan('dtx:convert', ['collection' => $collection])
        ->expectsOutput("Downloading DTX file from: {$url}")
        ->expectsOutput('DTX file saved to: '.storage_path("app/private/dtximport/{$collection}.dtx"))
        ->expectsOutput('No songs found in DTX file.')
        ->assertFailed();

    // Clean up DTX file (command deletes it on failure? Actually it does not delete because it returns early)
    $dtxPath = storage_path("app/private/dtximport/{$collection}.dtx");
    if (file_exists($dtxPath)) {
        unlink($dtxPath);
    }
});

test('dtx convert command assigns incremental batch numbers', function () {
    // Create existing bulk import with batch number 3
    BulkImport::factory()->create(['batch_number' => 3]);

    $collection = 'batchtest_'.uniqid();
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    $dtxContent = <<<'DTX'
;Test collection
RBatchTest
S1

>1
/1 First song
 A test song line.

>2
/1 Second song
 Another line.
DTX;

    Http::fake([
        $url => Http::response($dtxContent, 200),
    ]);

    // Run command
    $this->artisan('dtx:convert', ['collection' => $collection])->assertSuccessful();

    // Verify records have batch number 4 (max + 1)
    $records = BulkImport::where('collection', $collection)->get();
    expect($records)->toHaveCount(2);
    expect($records[0]->batch_number)->toBe(4);
    expect($records[1]->batch_number)->toBe(4);

    // Run command again with different collection, should get batch number 5
    $collection2 = 'batchtest2_'.uniqid();
    $url2 = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection2}.dtx";
    Http::fake([
        $url2 => Http::response($dtxContent, 200),
    ]);
    $this->artisan('dtx:convert', ['collection' => $collection2])->assertSuccessful();

    $records2 = BulkImport::where('collection', $collection2)->get();
    expect($records2)->toHaveCount(2);
    expect($records2[0]->batch_number)->toBe(5);

    // Clean up CSV files
    $csvPath = storage_path("app/private/dtximport/{$collection}.csv");
    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
    $csvPath2 = storage_path("app/private/dtximport/{$collection2}.csv");
    if (file_exists($csvPath2)) {
        unlink($csvPath2);
    }
});

test('dtx convert command assigns batch number 1 when no existing records', function () {
    // Ensure no bulk imports exist
    BulkImport::query()->delete();

    $collection = 'batchfirst_'.uniqid();
    $url = "https://raw.githubusercontent.com/diatar/diatar-dtxs/refs/heads/main/{$collection}.dtx";

    $dtxContent = <<<'DTX'
;Test collection
RFirst
S1

>1
/1 First song
 A test song line.
DTX;

    Http::fake([
        $url => Http::response($dtxContent, 200),
    ]);

    $this->artisan('dtx:convert', ['collection' => $collection])->assertSuccessful();

    $records = BulkImport::where('collection', $collection)->get();
    expect($records)->toHaveCount(1);
    expect($records[0]->batch_number)->toBe(1);

    // Clean up CSV
    $csvPath = storage_path("app/private/dtximport/{$collection}.csv");
    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
});
