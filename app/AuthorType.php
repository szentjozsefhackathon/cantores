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
}
