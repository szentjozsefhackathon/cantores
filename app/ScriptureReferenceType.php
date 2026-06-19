<?php

namespace App;

enum ScriptureReferenceType: string
{
    case Exact = 'exact';
    case LooselyRelated = 'loosely_related';

    public function label(): string
    {
        return __($this->name);
    }
}
