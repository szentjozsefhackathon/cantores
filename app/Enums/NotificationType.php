<?php

namespace App\Enums;

enum NotificationType: string
{
    case ERROR_REPORT = 'error_report';
    case CONTACT_MESSAGE = 'contact_message';
    case RIGHTS_REPORT = 'rights_report';
    // Future expansion: SYSTEM = 'system', MENTION = 'mention', etc.

    public function label(): string
    {
        return match ($this) {
            self::ERROR_REPORT => __('Error report'),
            self::CONTACT_MESSAGE => __('Contact message'),
            self::RIGHTS_REPORT => __('Rights complaint'),
        };
    }
}
