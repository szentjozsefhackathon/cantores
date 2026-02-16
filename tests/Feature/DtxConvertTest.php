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
