<?php

use App\Services\Diatar\DiatarDtxMetadataParser;

it('extracts unicode metadata without retaining lyrics', function () {
    $contents = file_get_contents(dirname(__DIR__, 3).'/Fixtures/Diatar/ordinary.dtx');

    $result = (new DiatarDtxMetadataParser)->parse('szvu.dtx', $contents);

    expect($result['book'])
        ->title->toBe('Próba énekeskönyv')
        ->short_name->toBe('PR')
        ->group->toBe('Tesztgyűjtemény')
        ->source_order->toBe(7)
        ->available->toBeTrue()
        ->and($result['songs'][0]['title'])->toBe('230 Örömünk forrása')
        ->and($result['songs'][0]['reference'])->toBe('230')
        ->and($result['songs'][0]['slides'][0])->toMatchArray([
            'source_order' => 1,
            'external_id' => 'ABCDEF12',
            'verse_name' => '1. versszak',
            'is_exportable' => true,
            'diagnostic_reason' => null,
        ])
        ->and(json_encode($result))->not->toContain('kitalált tesztsor');
});

it('supports CRLF and continues after malformed records', function () {
    $contents = file_get_contents(dirname(__DIR__, 3).'/Fixtures/Diatar/malformed.dtx');
    $contents = str_replace("\n", "\r\n", $contents);

    $result = (new DiatarDtxMetadataParser)->parse('mixed.dtx', $contents);

    expect($result['songs'])->toHaveCount(3)
        ->and($result['songs'][0]['slides'][0]['is_exportable'])->toBeTrue()
        ->and($result['songs'][0]['slides'][1]['diagnostic_reason'])->toBe('invalid_slide_id')
        ->and($result['songs'][1]['slides'][0]['is_exportable'])->toBeFalse()
        ->and($result['songs'][1]['slides'][1]['diagnostic_reason'])->toBe('duplicate_external_id')
        ->and($result['songs'][1]['available'])->toBeFalse()
        ->and($result['songs'][2]['available'])->toBeFalse()
        ->and($result['warnings'])->not->toBeEmpty();
});

it('marks a wholly uninterpretable source unavailable without throwing', function () {
    $result = (new DiatarDtxMetadataParser)->parse('broken.dtx', "not dtx\ntext only\n");

    expect($result['book']['available'])->toBeFalse()
        ->and($result['songs'])->toBeEmpty()
        ->and($result['warnings'])->toContain('The book contains no safely exportable songs.');
});
