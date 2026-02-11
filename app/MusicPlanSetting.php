<?php

namespace App;

enum MusicPlanSetting: string
{
    case ORGANIST = 'organist';
    case GUITARIST = 'guitarist';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ORGANIST => __('Organist'),
            self::GUITARIST => __('Guitarist'),
            self::OTHER => __('Other'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ORGANIST => 'organist',
            self::GUITARIST => 'guitarist',
            self::OTHER => 'other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ORGANIST => 'blue',
            self::GUITARIST => 'green',
            self::OTHER => 'gray',
        };
    }

    public static function options(): array
    {
        return [
            self::ORGANIST->value => self::ORGANIST->label(),
            self::GUITARIST->value => self::GUITARIST->label(),
            self::OTHER->value => self::OTHER->label(),
        ];
    }
}
