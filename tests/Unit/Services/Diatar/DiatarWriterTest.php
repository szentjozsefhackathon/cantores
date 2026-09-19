<?php

use App\Services\Diatar\DiatarWriter;

it('writes exact UTF-8 DIA output and preserves repeated slides', function () {
    $contents = (new DiatarWriter)->write([
        [
            'external_id' => 'abcdef12',
            'book_title' => 'Próba énekeskönyv',
            'song_title' => '230 Örömünk forrása',
            'verse_name' => '1. versszak',
        ],
        [
            'external_id' => 'abcdef12',
            'book_title' => 'Próba énekeskönyv',
            'song_title' => '230 Örömünk forrása',
            'verse_name' => '1. versszak',
        ],
    ]);

    expect($contents)->toBe(<<<'DIA'
[main]
diaszam=2
utf8=1

[1]
id=ABCDEF12
kotet=Próba énekeskönyv
enek=230 Örömünk forrása
versszak=1. versszak

[2]
id=ABCDEF12
kotet=Próba énekeskönyv
enek=230 Örömünk forrása
versszak=1. versszak
DIA."\n");
});
