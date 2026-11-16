<?php

namespace App\Services;

/**
 * Tidies psalm verses pasted from a printed or online source into the form
 * the tone setter reads.
 *
 * The verse number, `(1)` or `1.`, and a leading `R.`, `V.` or `ANT.` become
 * a marker joined to the verse by `~~`; an `R.`, `V.` or `ANT.` at the end of
 * a line, the response to be repeated, is put in brackets so it takes no
 * syllable; and the `/` the sources stress syllables with is dropped, since
 * the setter finds the stresses itself. Running the clean-up twice changes
 * nothing more.
 */
class PsalmSourceCleaner
{
    /** The abbreviations of the responses and the antiphon a psalm repeats. */
    private const RESPONSES = 'R|V|ANT';

    public function clean(string $verses): string
    {
        $lines = array_map($this->cleanLine(...), preg_split('/\R/u', $verses) ?: []);

        return trim(implode("\n", $lines), "\n");
    }

    private function cleanLine(string $line): string
    {
        $line = trim((string) preg_replace('/[^\S\n]+/u', ' ', $line));
        $line = (string) preg_replace('#/(?=\p{L})#u', '', $line);

        if (preg_match('/^(?:\((\d+)\)|(\d+)\.)\s+(?=\S)/u', $line, $match)) {
            $line = ($match[1] !== '' ? $match[1] : $match[2]).'.~~'.substr($line, strlen($match[0]));
        } elseif (preg_match('/^('.self::RESPONSES.')\.\s+(?=\S)/iu', $line, $match)) {
            $line = $match[1].'.~~'.substr($line, strlen($match[0]));
        }

        return (string) preg_replace('/\s+('.self::RESPONSES.')\.$/iu', ' ($1.)', $line);
    }
}
