<?php

namespace App\Services\Diatar;

class DiatarDtxMetadataParser
{
    /**
     * @return array{
     *     book: array{title: string, short_name: ?string, group: ?string, source_order: int, available: bool, diagnostic_reason: ?string},
     *     songs: list<array{title: string, reference: ?string, source_order: int, available: bool, diagnostic_reason: ?string, slides: list<array{source_order: int, external_id: ?string, verse_name: string, is_exportable: bool, diagnostic_reason: ?string}>}>,
     *     warnings: list<string>
     * }
     */
    public function parse(string $sourcePath, string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $bookTitle = pathinfo($sourcePath, PATHINFO_FILENAME);
        $shortName = null;
        $group = null;
        $bookOrder = 0;
        $songs = [];
        $warnings = [];
        $currentSong = null;
        $currentVerse = null;
        $pendingSlideId = null;
        $seenSong = false;

        $flushVerse = function () use (&$currentSong, &$currentVerse, &$pendingSlideId, &$warnings): void {
            if ($currentSong === null || $currentVerse === null) {
                return;
            }

            $rawId = $pendingSlideId;
            $externalId = is_string($rawId) && preg_match('/^[0-9a-f]{8}$/i', $rawId)
                ? mb_strtoupper($rawId)
                : null;
            $reason = null;

            if ($rawId === null || $rawId === '') {
                $reason = 'missing_slide_id';
                $warnings[] = "Missing slide ID in {$currentSong['title']} ({$currentVerse['name']}).";
            } elseif ($externalId === null) {
                $reason = 'invalid_slide_id';
                $warnings[] = "Invalid slide ID in {$currentSong['title']} ({$currentVerse['name']}).";
            }

            $currentSong['slides'][] = [
                'source_order' => count($currentSong['slides']) + 1,
                'external_id' => $externalId,
                'verse_name' => $currentVerse['name'],
                'is_exportable' => $externalId !== null,
                'diagnostic_reason' => $reason,
            ];

            $currentVerse = null;
            $pendingSlideId = null;
        };

        $flushSong = function () use (&$currentSong, &$songs, &$warnings, $flushVerse): void {
            if ($currentSong === null) {
                return;
            }

            $flushVerse();
            $hasExportableSlide = collect($currentSong['slides'])->contains('is_exportable', true);
            $hasTitle = $currentSong['title'] !== '';
            $currentSong['available'] = $hasTitle && $hasExportableSlide;
            $currentSong['diagnostic_reason'] = match (true) {
                ! $hasTitle => 'missing_song_title',
                ! $hasExportableSlide => 'no_exportable_slides',
                default => null,
            };

            if (! $currentSong['available']) {
                $warnings[] = $hasTitle
                    ? "Song {$currentSong['title']} has no exportable slides."
                    : 'A song has no title.';
            }

            $songs[] = $currentSong;
            $currentSong = null;
        };

        foreach ($lines as $rawLine) {
            if ($rawLine === '') {
                continue;
            }

            if (! $seenSong) {
                if (str_starts_with($rawLine, '>')) {
                    $seenSong = true;
                } elseif (str_starts_with($rawLine, 'N')) {
                    $value = trim(mb_substr($rawLine, 1));
                    $bookTitle = $value !== '' ? $value : $bookTitle;

                    continue;
                } elseif (str_starts_with($rawLine, 'R')) {
                    $value = trim(mb_substr($rawLine, 1));
                    $shortName = $value !== '' ? $value : null;

                    continue;
                } elseif (str_starts_with($rawLine, 'C')) {
                    $value = trim(mb_substr($rawLine, 1));
                    $group = $value !== '' ? $value : null;

                    continue;
                } elseif (str_starts_with($rawLine, 'S')) {
                    $bookOrder = (int) trim(mb_substr($rawLine, 1));

                    continue;
                } else {
                    continue;
                }
            }

            if (str_starts_with($rawLine, '>')) {
                $flushSong();
                $title = trim(mb_substr($rawLine, 1));
                $currentSong = [
                    'title' => $title,
                    'reference' => $this->parseReference($title),
                    'source_order' => count($songs) + 1,
                    'available' => false,
                    'diagnostic_reason' => null,
                    'slides' => [],
                ];
                $currentVerse = ['name' => '', 'has_body' => false];
                $pendingSlideId = null;

                continue;
            }

            if ($currentSong === null) {
                continue;
            }

            if (str_starts_with($rawLine, '/')) {
                if ($currentVerse !== null && ($currentVerse['has_body'] || $pendingSlideId !== null)) {
                    $flushVerse();
                }
                $currentVerse = [
                    'name' => trim(mb_substr($rawLine, 1)),
                    'has_body' => false,
                ];

                continue;
            }

            if (str_starts_with($rawLine, '#')) {
                if ($pendingSlideId !== null) {
                    $warnings[] = "More than one slide ID precedes a verse in {$currentSong['title']}.";
                }
                $pendingSlideId = trim(mb_substr($rawLine, 1));

                continue;
            }

            if (str_starts_with($rawLine, ' ') || str_starts_with($rawLine, "\t") || str_starts_with($rawLine, '\\')) {
                $currentVerse ??= ['name' => '', 'has_body' => false];
                $currentVerse['has_body'] = true;
            }
        }

        $flushSong();
        $this->quarantineDuplicates($songs, $warnings);

        foreach ($songs as &$song) {
            if ($song['available'] && ! collect($song['slides'])->contains('is_exportable', true)) {
                $song['available'] = false;
                $song['diagnostic_reason'] = 'no_exportable_slides';
                $warnings[] = "Song {$song['title']} has no exportable slides.";
            }
        }
        unset($song);

        $available = collect($songs)->contains('available', true);
        if (! $available) {
            $warnings[] = 'The book contains no safely exportable songs.';
        }

        return [
            'book' => [
                'title' => $bookTitle,
                'short_name' => $shortName,
                'group' => $group,
                'source_order' => $bookOrder,
                'available' => $available,
                'diagnostic_reason' => $available ? null : 'no_exportable_songs',
            ],
            'songs' => $songs,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function parseReference(string $title): ?string
    {
        if (! preg_match('/^(\d+(?:[.\/-]?[\p{L}\d]+)?)(?:\s+|$)/u', $title, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param  list<array<string, mixed>>  $songs
     * @param  list<string>  $warnings
     */
    private function quarantineDuplicates(array &$songs, array &$warnings): void
    {
        $locations = [];

        foreach ($songs as $songIndex => $song) {
            foreach ($song['slides'] as $slideIndex => $slide) {
                if ($slide['external_id'] !== null) {
                    $locations[$slide['external_id']][] = [$songIndex, $slideIndex];
                }
            }
        }

        foreach ($locations as $externalId => $duplicates) {
            if (count($duplicates) < 2) {
                continue;
            }

            foreach ($duplicates as [$songIndex, $slideIndex]) {
                $songs[$songIndex]['slides'][$slideIndex]['is_exportable'] = false;
                $songs[$songIndex]['slides'][$slideIndex]['diagnostic_reason'] = 'duplicate_external_id';
            }

            $warnings[] = "Duplicate slide ID {$externalId}.";
        }
    }
}
