<?php

declare(strict_types=1);

namespace App\Sync\Enum;

enum SyncDirection: string
{
    case OdooToWooCommerce = 'odoo_to_woocommerce';
    case WooCommerceToOdoo = 'woocommerce_to_odoo';
}
