<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Reads what a .mscz can tell us without running MuseScore.
 *
 * A .mscz is a ZIP holding the score's .mscx XML and a thumbnail MuseScore
 * rendered when the file was last saved. Both are available the moment the
 * upload lands, so the editor can prefill the title and show a preview while
 * the render job is still queued.
 */
class MuseScoreMetadata
{
    /**
     * @return array{title: ?string, composer: ?string, lyricist: ?string, thumbnail: ?string}
     */
    public function read(string $mscz): array
    {
        $empty = ['title' => null, 'composer' => null, 'lyricist' => null, 'thumbnail' => null];

        $file = tempnam(sys_get_temp_dir(), 'mscz-');
        if ($file === false) {
            throw new RuntimeException('Unable to create a temporary file for reading the score.');
        }

        try {
            if (file_put_contents($file, $mscz) === false) {
                throw new RuntimeException('Unable to stage the score for reading.');
            }

            $zip = new ZipArchive;
            if ($zip->open($file, ZipArchive::RDONLY) !== true) {
                return $empty;
            }

            try {
                $mscx = $this->readScoreXml($zip);
                $metadata = $mscx === null ? $empty : $this->parseScoreXml($mscx);

                $thumbnail = $zip->getFromName('Thumbnails/thumbnail.png');
                $metadata['thumbnail'] = is_string($thumbnail) && str_starts_with($thumbnail, "\x89PNG")
                    ? $thumbnail
                    : null;

                return $metadata;
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($file);
        }
    }

    /**
     * The .mscx entry, whose name follows the score's own filename rather than
     * a fixed one — so it is found by extension, not by guessing.
     */
    private function readScoreXml(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && str_ends_with(strtolower($name), '.mscx')) {
                $contents = $zip->getFromIndex($index);

                if (is_string($contents) && $contents !== '') {
                    return $contents;
                }
            }
        }

        return null;
    }

    /**
     * @return array{title: ?string, composer: ?string, lyricist: ?string, thumbnail: ?string}
     */
    private function parseScoreXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;

        try {
            if (! $doc->loadXML($xml, LIBXML_NONET)) {
                return ['title' => null, 'composer' => null, 'lyricist' => null, 'thumbnail' => null];
            }

            $xpath = new \DOMXPath($doc);

            return [
                'title' => $this->metaTag($xpath, 'workTitle') ?? $this->frameText($xpath, 'title'),
                'composer' => $this->metaTag($xpath, 'composer') ?? $this->frameText($xpath, 'composer'),
                'lyricist' => $this->metaTag($xpath, 'lyricist') ?? $this->frameText($xpath, 'lyricist'),
                'thumbnail' => null,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function metaTag(\DOMXPath $xpath, string $name): ?string
    {
        $nodes = $xpath->query(sprintf('//metaTag[@name=%s]', $this->quote($name)));

        return $this->firstNonEmpty($nodes);
    }

    /**
     * The text of the title frame, used when the metaTag is absent or blank —
     * scores imported from MusicXML often carry their title only there.
     */
    private function frameText(\DOMXPath $xpath, string $style): ?string
    {
        $nodes = $xpath->query(sprintf('//Text[style=%s]/text', $this->quote($style)));

        return $this->firstNonEmpty($nodes);
    }

    /**
     * @param  \DOMNodeList<\DOMNode>|false  $nodes
     */
    private function firstNonEmpty(\DOMNodeList|false $nodes): ?string
    {
        if ($nodes === false) {
            return null;
        }

        foreach ($nodes as $node) {
            $value = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if ($value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return null;
    }

    /**
     * XPath 1.0 has no escape syntax, so a value containing a quote has to be
     * assembled with concat().
     */
    private function quote(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return 'concat('.implode(", \"'\", ", array_map(
            fn (string $part): string => "'".$part."'",
            explode("'", $value)
        )).')';
    }
}
