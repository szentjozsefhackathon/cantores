<?php

namespace App\Enums;

enum DiatarSyncStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Failed = 'failed';
}
