<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;

interface BusinessDirectory
{
    /**
     * @throws SubscriptionBusinessNotFound
     */
    public function idForSlug(string $slug): string;

    /**
     * @throws SubscriptionBusinessNotFound
     */
    public function timezoneOf(string $businessId): string;
}
