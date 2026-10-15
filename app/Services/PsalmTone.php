<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * A psalm tone: the initium, flexa, mediatio and terminatio a verse is sung to.
 *
 * It is written on one line, as the tone files and the custom formula field
 * have it: an optional key signature, then the four formulas between bar
 * tokens standing on their own, `initium | flexa , mediatio | terminatio ||`.
 * A tone file may give it a `w:` line too, which the preview of the tone shows
 * under its notes.
 */
final readonly class PsalmTone
{
    /** The bar tokens that close the four formulas, in order. */
    private const SEPARATORS = ['|', ',', '|', '||'];

    /**
     * @param  list<PsalmToneSlot>  $initium
     * @param  list<PsalmToneSlot>  $flexa
     * @param  list<PsalmToneSlot>  $mediatio
     * @param  list<PsalmToneSlot>  $terminatio
     */
    public function __construct(
        public string $name,
        public string $line,
        public string $keySignature,
        public array $initium,
        public array $flexa,
        public array $mediatio,
        public array $terminatio,
        public ?string $lyrics = null,
    ) {}

    /**
     * The tone as a score of its own, with the flexa and the mediant marked
     * under their bars.
     */
    public function preview(): string
    {
        $lyrics = $this->lyrics;

        if ($lyrics === null) {
            $lyrics = implode(' ', [
                ...array_fill(0, count($this->initium) + count($this->flexa), '~'),
                $this->flexa === [] ? '()' : '(+)',
                ...array_fill(0, count($this->mediatio), '~'),
                '(*)',
            ]);
        }

        return '(g2) '.$this->line."\nw: ".$lyrics;
    }

    /**
     * @throws InvalidArgumentException when the line is not a tone
     */
    public static function fromLine(string $name, string $line, ?string $lyrics = null): self
    {
        $tokens = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keySignature = [];

        while ($tokens !== [] && preg_match('/^\(K[^()]*\)$/u', $tokens[0])) {
            $keySignature[] = array_shift($tokens);
        }

        $parts = [];
        $part = [];
        $accidental = '';

        foreach ($tokens as $token) {
            if ($token === (self::SEPARATORS[count($parts)] ?? null)) {
                $parts[] = $part;
                $part = [];

                continue;
            }

            if (count($parts) === count(self::SEPARATORS) || in_array($token, self::SEPARATORS, true)) {
                throw new InvalidArgumentException(__('Unexpected ":token" in the tone. Write it as: initium | flexa , mediatio | terminatio ||', ['token' => $token]));
            }

            if (preg_match('/^\([^()]*\)$/u', $token)) {
                $accidental .= $token;

                continue;
            }

            $part[] = PsalmToneSlot::fromToken($accidental.$token);
            $accidental = '';
        }

        if (count($parts) !== count(self::SEPARATORS) || $part !== []) {
            throw new InvalidArgumentException(__('The tone needs four parts: initium | flexa , mediatio | terminatio ||'));
        }

        [$initium, $flexa, $mediatio, $terminatio] = $parts;

        if ($mediatio === [] || $terminatio === []) {
            throw new InvalidArgumentException(__('The tone needs a mediatio and a terminatio.'));
        }

        foreach ($parts as $slots) {
            if (count(array_filter($slots, fn (PsalmToneSlot $slot): bool => $slot->isTenor)) > 1) {
                throw new InvalidArgumentException(__('A formula of the tone has more than one tenor.'));
            }
        }

        return new self($name, trim($line), implode(' ', $keySignature), $initium, $flexa, $mediatio, $terminatio, $lyrics);
    }
}
