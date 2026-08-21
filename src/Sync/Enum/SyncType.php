<?php

declare(strict_types=1);

namespace App\Sync\Enum;

enum SyncType: string
{
    case Product = 'product';
    case Category = 'category';
    case Brand = 'brand';
    case Order = 'order';
    case Webhook = 'webhook';
}
