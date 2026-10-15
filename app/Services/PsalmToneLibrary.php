<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * The psalm-tone collections shipped in `resources/psalm-tones`.
 *
 * A collection file is an Aretino score that names itself with a comment and
 * then lists its tones, each a comment with its name, its formula line and, if
 * the preview should mark the flexa and the mediant, a `w:` line:
 *
 *     %collection: Bencés
 *     %I.g
 *     (Kb) f g | at g , at b- ao g- ao | at g f a- go ||
 *     w: ~ ~ ~ ~ (+) ~ ~ ~ ~ ~ (*)
 */
class PsalmToneLibrary
{
    /** @var array<string, array{name: string, tones: array<string, PsalmTone>}>|null */
    private ?array $collections = null;

    public function __construct(private ?string $directory = null) {}

    /**
     * The collections by file name, in file-name order.
     *
     * @return array<string, array{name: string, tones: array<string, PsalmTone>}>
     */
    public function collections(): array
    {
        if ($this->collections !== null) {
            return $this->collections;
        }

        $paths = glob(($this->directory ?? resource_path('psalm-tones')).'/*.aretino') ?: [];
        sort($paths);
        $collections = [];

        foreach ($paths as $path) {
            $collections[pathinfo($path, PATHINFO_FILENAME)] = self::parse((string) file_get_contents($path), basename($path));
        }

        return $this->collections = $collections;
    }

    public function tone(string $collection, string $name): ?PsalmTone
    {
        return $this->collections()[$collection]['tones'][$name] ?? null;
    }

    /**
     * @return array{name: string, tones: array<string, PsalmTone>}
     *
     * @throws InvalidArgumentException naming the file and line that is wrong
     */
    public static function parse(string $contents, string $fileName): array
    {
        $lines = preg_split('/\R/u', $contents) ?: [];
        $name = $fileName;
        $tones = [];
        $toneName = null;
        $formula = null;

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if (preg_match('/^%collection:\s*(.+)$/u', $line, $match)) {
                $name = trim($match[1]);
            } elseif (str_starts_with($line, '%')) {
                $toneName = trim(substr($line, 1)) ?: null;
            } elseif (str_starts_with($line, 'w:')) {
                if ($formula !== null) {
                    $tones[$formula['name']] = self::toneFromFormula($formula, trim(substr($line, 2)), $fileName);
                    $formula = null;
                }
            } elseif ($line !== '') {
                if ($formula !== null) {
                    $tones[$formula['name']] = self::toneFromFormula($formula, null, $fileName);
                }

                if ($toneName === null) {
                    throw new InvalidArgumentException("{$fileName}:".($index + 1).': '.__('The formula has no tone name before it.'));
                }

                $formula = ['name' => $toneName, 'line' => $line, 'number' => $index + 1];
                $toneName = null;
            }
        }

        if ($formula !== null) {
            $tones[$formula['name']] = self::toneFromFormula($formula, null, $fileName);
        }

        return ['name' => $name, 'tones' => $tones];
    }

    /**
     * @param  array{name: string, line: string, number: int}  $formula
     *
     * @throws InvalidArgumentException naming the file and line that is wrong
     */
    private static function toneFromFormula(array $formula, ?string $lyrics, string $fileName): PsalmTone
    {
        try {
            return PsalmTone::fromLine($formula['name'], $formula['line'], $lyrics);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException("{$fileName}:{$formula['number']}: ".$exception->getMessage(), previous: $exception);
        }
    }
}
