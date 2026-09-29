<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

enum BillingInterval: string
{
    case Month = 'month';
}
