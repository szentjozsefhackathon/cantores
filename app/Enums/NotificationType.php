<?php

namespace App\Enums;

enum NotificationType: string
{
    case ERROR_REPORT = 'error_report';
    case CONTACT_MESSAGE = 'contact_message';
    // Future expansion: SYSTEM = 'system', MENTION = 'mention', etc.
}
