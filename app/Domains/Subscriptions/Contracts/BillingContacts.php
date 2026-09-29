<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Contracts;

use App\Domains\Subscriptions\Exceptions\SubscriptionBusinessNotFound;
use App\Domains\Subscriptions\ValueObjects\BillingContact;

interface BillingContacts
{
    /**
     * @throws SubscriptionBusinessNotFound
     */
    public function ownerOf(string $businessId): BillingContact;
}
