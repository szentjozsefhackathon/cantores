<?php

namespace App;

enum MusicUrlLabel: string
{
    case SheetMusic = 'sheet_music';
    case Audio = 'audio';
    case Video = 'video';
    case Text = 'text';
    case Information = 'information';

    public function label(): string
    {
        return match($this) {
            self::SheetMusic => __('Sheet Music'),
            self::Audio => __('Audio'),
            self::Video => __('Video'),
            self::Text => __('Text'),
            self::Information => __('Information'),
        };
    }
}
