<?php

namespace App;

enum AuthorType: string
{
    case Composer = 'composer';
    case Lyricist = 'lyricist';

    public function label(): string
    {
        return __($this->name);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Composer => 'music-2',
            self::Lyricist => 'pen-tool',
        };
    }

    /**
     * Options for select inputs, keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $type): array => $carry + [$type->value => $type->label()],
            [],
        );
    }
}
