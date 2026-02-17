<?php

namespace App\Enums;

enum NotificationType: string
{
    case ERROR_REPORT = 'error_report';
    // Future expansion: SYSTEM = 'system', MENTION = 'mention', etc.
}
