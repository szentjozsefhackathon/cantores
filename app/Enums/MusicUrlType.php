<?php

namespace App\Enums;

enum MusicUrlType: string
{
    case SheetMusic = 'sheet_music';
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::SheetMusic => __('Sheet Music'),
            self::Audio => __('Audio'),
            self::Video => __('Video'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SheetMusic => 'file-music',
            self::Audio => 'speaker-wave',
            self::Video => 'video-camera',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SheetMusic => 'text-purple-500',
            self::Audio => 'text-amber-500',
            self::Video => 'text-red-500',
        };
    }

    /**
     * Try to get the enum from a label string.
     */
    public static function tryFromLabel(?string $label): ?self
    {
        if ($label === null) {
            return null;
        }

        return match ($label) {
            'sheet_music' => self::SheetMusic,
            'audio' => self::Audio,
            'video' => self::Video,
            default => null,
        };
    }
}
