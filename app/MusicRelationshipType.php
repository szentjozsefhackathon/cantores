<?php

namespace App;

enum MusicRelationshipType: string
{
    case Variation = 'variation'; // Variáció
    case Arrangement = 'arrangement'; // Feldolgozás
    case Accompaniment = 'accompaniment'; // Kíséret
    case Contrafact = 'contrafact'; // Kontrafaktum
    case SameSetting = 'same_setting'; // Összetartozó
    case Other = 'other'; // Egyéb
}
