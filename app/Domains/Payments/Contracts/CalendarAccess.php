<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\ValueObjects\CalendarScope;

interface CalendarAccess
{
    /**
     * @throws PaymentAccountNotFound
     */
    public function scopeFor(string $businessId, string $accountId): CalendarScope;
}
