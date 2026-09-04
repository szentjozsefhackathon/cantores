<?php

namespace App\Services;

use App\Models\ScorePublication;
use Illuminate\Support\HtmlString;

/**
 * Builds the one credit line a published score carries everywhere.
 *
 * The page, the listing badge, the JSON-LD and the exported PDF footer all read
 * from here, so a score cannot end up attributed one way in a listing and
 * another way in the file someone downloads.
 */
class ScoreAttributionBuilder
{
    /**
     * The plain-text credit, for headers, PDF footers and meta descriptions.
     */
    public function line(ScorePublication $publication): string
    {
        if ($publication->attribution_line !== null && $publication->attribution_line !== '') {
            return $publication->attribution_line;
        }

        $score = $publication->score;
        $license = $publication->effectiveLicense();

        $parts = array_filter([
            $score->title,
            $this->composers($publication),
            $publication->source_title,
            $license->shortCode(),
        ], fn (?string $part): bool => $part !== null && $part !== '');

        return implode(' · ', $parts);
    }

    /**
     * The credit as it appears on the page, with the licence linked.
     */
    public function html(ScorePublication $publication): HtmlString
    {
        $license = $publication->effectiveLicense();
        $deedUrl = $license->deedUrl();

        $text = e($this->line($publication));

        if ($deedUrl === null) {
            return new HtmlString($text);
        }

        $link = sprintf(
            '<a href="%s" rel="license noopener" target="_blank" class="underline">%s</a>',
            e($deedUrl),
            e($license->shortCode()),
        );

        // The short code is already the last segment of the line, so it is
        // replaced in place rather than appended a second time.
        $plain = e($license->shortCode());

        return new HtmlString(str_replace($plain, $link, $text));
    }

    /**
     * schema.org MusicComposition for the public page.
     *
     * @return array<string, mixed>
     */
    public function structuredData(ScorePublication $publication): array
    {
        $score = $publication->score;
        $license = $publication->effectiveLicense();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'MusicComposition',
            'name' => $score->title,
            'isAccessibleForFree' => true,
            'url' => route('public-scores.show', ['score' => $score, 'slug' => str($score->title)->slug()->value()]),
        ];

        if ($license->deedUrl() !== null) {
            $data['license'] = $license->deedUrl();
        }

        $composers = $this->composers($publication);

        if ($composers !== null) {
            $data['composer'] = ['@type' => 'Person', 'name' => $composers];
        }

        if ($publication->published_at !== null) {
            $data['datePublished'] = $publication->published_at->toDateString();
        }

        $encodings = [];

        foreach ($score->publishedFiles() as $file) {
            $encodings[] = array_filter([
                '@type' => 'MediaObject',
                'contentUrl' => route('public-scores.file.download', ['score' => $score, 'scoreFile' => $file]),
                'encodingFormat' => $file->mime,
                'license' => $license->deedUrl(),
            ]);
        }

        if ($encodings !== []) {
            $data['encoding'] = $encodings;
        }

        return $data;
    }

    /**
     * The composers and lyricists named on the attached music, if any.
     */
    private function composers(ScorePublication $publication): ?string
    {
        $music = $publication->score->music;

        if ($music === null) {
            return null;
        }

        $names = $music->authors->pluck('name')->filter()->unique()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }
}
