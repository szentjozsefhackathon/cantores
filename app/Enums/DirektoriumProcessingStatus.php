<?php

namespace App\Enums;

enum DirektoriumProcessingStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
