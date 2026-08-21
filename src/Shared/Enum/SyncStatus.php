<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
