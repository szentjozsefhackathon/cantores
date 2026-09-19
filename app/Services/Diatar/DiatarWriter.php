<?php

namespace App\Services\Diatar;

class DiatarWriter
{
    /**
     * @param  list<array{external_id: string, book_title: string, song_title: string, verse_name: string}>  $slides
     */
    public function write(array $slides): string
    {
        $lines = [
            '[main]',
            'diaszam='.count($slides),
            'utf8=1',
        ];

        foreach ($slides as $index => $slide) {
            $lines[] = '';
            $lines[] = '['.($index + 1).']';
            $lines[] = 'id='.mb_strtoupper($slide['external_id']);
            $lines[] = 'kotet='.$slide['book_title'];
            $lines[] = 'enek='.$slide['song_title'];
            $lines[] = 'versszak='.$slide['verse_name'];
        }

        return implode("\n", $lines)."\n";
    }
}
