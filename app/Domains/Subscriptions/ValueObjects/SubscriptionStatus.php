<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

enum SubscriptionStatus: string
{
    case Active = 'active';

    case Canceled = 'canceled';

    case Expired = 'expired';
}
