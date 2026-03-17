<?php

namespace App;

enum MusicRelationshipType: string
{
    case Variation = 'variation';
    case Arrangement = 'arrangement';
    case Accompaniment = 'accompaniment';
    case Contrafact = 'contrafact';
    case Connected = 'connected';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function label(): string
    {
        return __($this->name);
    }
}
