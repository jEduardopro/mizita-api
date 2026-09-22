<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

enum DiscountType: string
{
    case None = 'none';

    case Percentage = 'percentage';

    case Fixed = 'fixed';
}
