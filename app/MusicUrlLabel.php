<?php

namespace App;

enum MusicUrlLabel: string
{
    case SheetMusic = 'sheet_music';
    case Audio = 'audio';
    case Video = 'video';
    case Text = 'text';
    case Information = 'information';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SheetMusic => __('Sheet Music'),
            self::Audio => __('Audio'),
            self::Video => __('Video'),
            self::Text => __('Text'),
            self::Information => __('Information'),
            self::Other => __('Other')
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SheetMusic => 'file-music',
            self::Audio => 'speaker-wave',
            self::Video => 'video-camera',
            self::Text => 'file-text',
            self::Information => 'information-circle',
            self::Other => 'link',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SheetMusic => 'text-purple-500',
            self::Audio => 'text-amber-500',
            self::Video => 'text-red-500',
            self::Text => 'text-blue-500',
            self::Information => 'text-green-500',
            self::Other => 'text-gray-500',
        };
    }

    public static function tryFromLabel(?string $label): ?self
    {
        if ($label === null) {
            return null;
        }

        return self::tryFrom($label);
    }
}
