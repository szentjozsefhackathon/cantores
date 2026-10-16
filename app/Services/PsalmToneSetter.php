<?php

namespace App\Services;

use App\Enums\InitiumSetting;

/**
 * Sets Hungarian psalm verses to a psalm tone and writes them as Aretino.
 *
 * A verse is one line of the text, its halves divided by `*` and a flexa
 * marked with `+` or `†`. A line without `*` is sung to the terminatio alone,
 * and a line that ends with a mark goes on in the next one.
 * Every verse becomes a block of its own, a melody line and its `w:` line, and
 * the marks of the text are written into the lyrics as `(+)` and `(*)`, which
 * the renderer sets under the bar that closes the part. The verses are also
 * written as running text, with the syllable where the melody leaves a
 * recited note and the accented syllables underlined: as HTML, a paragraph
 * each, as Aretino `W:` blocks and as ChordPro lines.
 */
class PsalmToneSetter
{
    /** Hungarian consonants written with two letters, which double by their first letter. */
    private const DIGRAPHS = ['cs', 'dz', 'gy', 'ly', 'ny', 'sz', 'ty', 'zs'];

    public function __construct(
        private HungarianStress $stress,
        private PsalmFormulaFitter $fitter,
    ) {}

    /**
     * @return array{source: string, html: string, aretinoText: string, chordProText: string, unsure: list<array{verse: int, syllable: string}>}
     */
    public function set(PsalmTone $tone, string $verses, InitiumSetting $initiumSetting = InitiumSetting::FirstVerse): array
    {
        $lines = [];
        $continues = false;

        foreach (preg_split('/\R/u', $verses) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($continues) {
                $lines[] = array_pop($lines).' '.$line;
            } else {
                $lines[] = $line;
            }

            $continues = preg_match('/[*+†]$/u', $line) === 1;
        }

        $blocks = [];
        $texts = [];
        $unsure = [];

        foreach ($lines as $index => $line) {
            $initium = $initiumSetting->appliesTo($index) ? $this->initium($tone->initium, $line) : [];
            $parts = [];

            foreach ($this->parts($tone, $line, $initium) as [$slots, $text, $mark, $bar]) {
                $fit = $this->fitter->fit($slots, $this->stress->syllables($text));
                $parts[] = [$fit['placements'], $mark, $bar];

                foreach ($fit['placements'] as $placement) {
                    if ($placement['missesAccent']) {
                        $unsure[] = ['verse' => $index + 1, 'syllable' => $placement['syllables'][0]->text];
                    }
                }
            }

            $prefix = $index === 0 ? trim('(g2) '.$tone->keySignature) : '(Z)';
            $blocks[] = $prefix.' '.$this->melody($parts)."\nw: ".$this->lyrics($parts);
            $texts[] = $this->words($parts);
        }

        return [
            'source' => implode("\n\n", $blocks),
            'html' => implode("\n", array_map(fn (array $words): string => '<p>'.self::underlined($words, self::htmlPiece(...)).'</p>', $texts)),
            'aretinoText' => implode("\n", array_map(fn (array $words): string => 'W: '.self::underlined($words, self::aretinoPiece(...)), $texts)),
            'chordProText' => implode("\n", array_map(fn (array $words): string => self::underlined($words, self::chordProPiece(...)), $texts)),
            'unsure' => $unsure,
        ];
    }

    /**
     * The initium as the verse sings it: on a verse that begins with a
     * one-syllable word, stressed or not, its notes are bent together onto it.
     *
     * @param  list<PsalmToneSlot>  $initium
     * @return list<PsalmToneSlot>
     */
    private function initium(array $initium, string $line): array
    {
        if (count($initium) < 2) {
            return $initium;
        }

        $firstWord = (string) strtok($line, " \t");
        if (count($this->stress->syllables($firstWord)) !== 1) {
            return $initium;
        }

        return [PsalmToneSlot::fromToken(implode('', array_map(fn (PsalmToneSlot $slot): string => $slot->notes, $initium)))];
    }

    /**
     * The parts of a verse with the slots each is sung to, the mark that ends
     * it in the text and the bar that ends it in the melody.
     *
     * @param  list<PsalmToneSlot>  $initium
     * @return list<array{0: list<PsalmToneSlot>, 1: string, 2: string, 3: string}>
     */
    private function parts(PsalmTone $tone, string $line, array $initium): array
    {
        if (! str_contains($line, '*')) {
            return [[$tone->terminatio, $line, '', '||']];
        }

        [$firstHalf, $secondHalf] = array_map('trim', explode('*', $line, 2));
        $parts = [];

        $firstHalf = str_replace('†', '+', $firstHalf);

        if ($tone->flexa === []) {
            $firstHalf = trim((string) preg_replace('/\s*\+\s*/u', ' ', $firstHalf));
        } elseif (str_contains($firstHalf, '+')) {
            [$flexed, $firstHalf] = array_map('trim', explode('+', $firstHalf, 2));
            $parts[] = [[...$initium, ...$tone->flexa], $flexed, '+', ','];
            $initium = [];
        }

        $parts[] = [[...$initium, ...$tone->mediatio], $firstHalf, '*', '|'];
        $parts[] = [$tone->terminatio, $secondHalf, '', '||'];

        return $parts;
    }

    /**
     * Writes the notes of the verse, the last note of every part dotted.
     *
     * @param  list<array{0: list<array{notes: string, syllables: list<PsalmSyllable>, isTenor: bool, continuesOpen: bool, isAccent: bool, missesAccent: bool}>, 1: string, 2: string}>  $parts
     */
    private function melody(array $parts): string
    {
        $tokens = [];

        foreach ($parts as [$placements, , $bar]) {
            $lastPlacement = array_key_last($placements);

            foreach ($placements as $index => $placement) {
                $tokens[] = $placement['notes'].($index === $lastPlacement ? '.' : '');
            }

            $tokens[] = $bar;
        }

        return implode(' ', $tokens);
    }

    /**
     * Writes the syllables under their notes: the syllables of one word on one
     * note run together, words on one note are tied with `~`, and a word
     * spread over several notes is hyphenated.
     *
     * @param  list<array{0: list<array{notes: string, syllables: list<PsalmSyllable>, isTenor: bool, continuesOpen: bool, isAccent: bool, missesAccent: bool}>, 1: string, 2: string}>  $parts
     */
    private function lyrics(array $parts): string
    {
        $lyrics = '';

        foreach ($parts as [$placements, $mark]) {
            $previous = null;

            foreach ($placements as $placement) {
                foreach ($placement['syllables'] as $position => $syllable) {
                    if ($previous !== null) {
                        $sameWord = $previous->word === $syllable->word;

                        if ($position > 0 && $sameWord) {
                            $lyrics = self::undoubleDigraph($lyrics, $syllable->text);
                        } else {
                            $lyrics .= match (true) {
                                $position > 0 => '~',
                                default => $sameWord ? '-' : ' ',
                            };
                        }
                    } elseif ($lyrics !== '') {
                        $lyrics .= ' ';
                    }

                    $lyrics .= self::escape($syllable->text);
                    $previous = $syllable;
                }
            }

            if ($mark !== '') {
                $lyrics .= ' ('.$mark.')';
            }
        }

        return $lyrics;
    }

    /**
     * Breaks the verse into running text with the flexa and the mediant marked,
     * and the syllable where the melody leaves the tenor, or an open note sung
     * on several syllables unless onto the last of the part, and the accented
     * syllables underlined, as the printed psalters have it. Of two such
     * syllables next to each other only the first is underlined.
     *
     * @param  list<array{0: list<array{notes: string, syllables: list<PsalmSyllable>, isTenor: bool, continuesOpen: bool, isAccent: bool, missesAccent: bool}>, 1: string, 2: string}>  $parts
     * @return list<list<array{text: string, isUnderlined: bool, isItalic?: bool, isMark?: bool}>>
     */
    private function words(array $parts): array
    {
        $words = [];
        $previous = null;
        $previousUnderlined = false;

        foreach ($parts as [$placements, $mark]) {
            $held = null;
            $lastPlacement = array_key_last($placements);

            foreach ($placements as $placementIndex => $placement) {
                $isLast = $placementIndex === $lastPlacement && count($placement['syllables']) === 1;
                $isDeparture = ! $placement['isTenor'] && ! $placement['continuesOpen']
                    && ($held === 'tenor' || ($held === 'open' && ! $isLast));
                $held = match (true) {
                    $placement['isTenor'] => 'tenor',
                    $placement['continuesOpen'] => 'open',
                    default => null,
                };

                foreach ($placement['syllables'] as $position => $syllable) {
                    $isMarked = $position === 0 && ($placement['isAccent'] || $isDeparture);
                    $piece = [
                        'text' => $syllable->text,
                        'isUnderlined' => $isMarked && ! $previousUnderlined,
                        'isItalic' => $position === 0 && self::isMelisma($placement['notes']),
                    ];
                    $previousUnderlined = $isMarked;

                    if ($previous !== null && $previous->word === $syllable->word && $words !== []) {
                        $word = array_pop($words);
                        $last = array_pop($word);
                        $last['text'] = self::undoubleDigraph($last['text'], $syllable->text);
                        $words[] = [...$word, $last, $piece];
                    } else {
                        $words[] = [$piece];
                    }

                    $previous = $syllable;
                }
            }

            if ($mark !== '') {
                $words[] = [['text' => $mark, 'isUnderlined' => false, 'isMark' => true]];
                $previous = null;
                $previousUnderlined = false;
            }
        }

        return $words;
    }

    /**
     * Writes the words of a verse, each piece in the markup of one format.
     *
     * @param  list<list<array{text: string, isUnderlined: bool, isItalic?: bool, isMark?: bool}>>  $words
     * @param  callable(array{text: string, isUnderlined: bool, isItalic?: bool, isMark?: bool}): string  $piece
     */
    private static function underlined(array $words, callable $piece): string
    {
        return implode(' ', array_map(
            fn (array $word): string => implode('', array_map($piece, $word)),
            $words,
        ));
    }

    /**
     * @param  array{text: string, isUnderlined: bool, isItalic?: bool, isMark?: bool}  $piece
     */
    private static function htmlPiece(array $piece): string
    {
        if ($piece['isMark'] ?? false) {
            return $piece['text'] === '+' ? '†' : e($piece['text']);
        }

        return self::decorate(e(str_replace('~', ' ', $piece['text'])), $piece, '<u>', '</u>', '<i>', '</i>');
    }

    /**
     * Aretino crosses a `+` itself and underlines between brackets.
     *
     * @param  array{text: string, isUnderlined: bool, isItalic?: bool, isMark?: bool}  $piece
     */
    private static function aretinoPiece(array $piece): string
    {
        if ($piece['isMark'] ?? false) {
            return $piece['text'];
        }

        return self::decorate(str_replace(['~', '|'], [' ', '\\|'], $piece['text']), $piece, '[', ']', '<', '>');
    }

    /**
     * @param  array{text: string, isUnderlined: bool, isItalic?: bool, isMark?: bool}  $piece
     */
    private static function chordProPiece(array $piece): string
    {
        if ($piece['isMark'] ?? false) {
            return $piece['text'] === '+' ? '†' : $piece['text'];
        }

        return self::decorate(str_replace('~', ' ', $piece['text']), $piece, '<u>', '</u>', '<i>', '</i>');
    }

    /**
     * A syllable sung to more than one note, accidentals aside.
     */
    private static function isMelisma(string $notes): bool
    {
        return preg_match_all('/[a-gA-G]/', (string) preg_replace('/\([^()]*\)/', '', $notes)) > 1;
    }

    /**
     * Wraps the letters of a syllable, not the punctuation around them, in
     * the markup for underlining and for the italic of a melisma.
     *
     * @param  array{isUnderlined: bool, isItalic?: bool}  $piece
     */
    private static function decorate(string $text, array $piece, string $underlineOpen, string $underlineClose, string $italicOpen, string $italicClose): string
    {
        $isItalic = $piece['isItalic'] ?? false;

        if (! $piece['isUnderlined'] && ! $isItalic) {
            return $text;
        }

        $open = ($piece['isUnderlined'] ? $underlineOpen : '').($isItalic ? $italicOpen : '');
        $close = ($isItalic ? $italicClose : '').($piece['isUnderlined'] ? $underlineClose : '');

        return (string) preg_replace('/^(\P{L}*)(.*\p{L})(\P{L}*)$/su', '$1'.$open.'$2'.$close.'$3', $text);
    }

    /**
     * Writes a doubled digraph the hyphenation spelt out on both sides of a
     * break (`visz-sza`) as one word spells it again (`vissza`).
     */
    private static function undoubleDigraph(string $lyrics, string $next): string
    {
        foreach (self::DIGRAPHS as $digraph) {
            if (str_ends_with(mb_strtolower($lyrics), $digraph) && str_starts_with(mb_strtolower($next), $digraph)) {
                return mb_substr($lyrics, 0, -mb_strlen($digraph) + 1);
            }
        }

        return $lyrics;
    }

    /** Characters that mean something in a `w:` line, written literally. */
    private static function escape(string $text): string
    {
        return str_replace('_', '\\_', $text);
    }
}
