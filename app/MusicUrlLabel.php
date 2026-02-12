<?php

namespace App;

enum MusicUrlLabel: string
{
    case SheetMusic = 'sheet_music';
    case Audio = 'audio';
    case Video = 'video';
    case Text = 'text';
    case Information = 'information';
}
