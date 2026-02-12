<?php

namespace App;

enum MusicRelationshipType: string
{
    case Variation = 'variation';
    case Arrangement = 'arrangement';
    case Transposition = 'transposition';
    case Adaptation = 'adaptation';
}
