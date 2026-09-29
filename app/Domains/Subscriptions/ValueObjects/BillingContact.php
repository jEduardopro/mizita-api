<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

final readonly class BillingContact
{
    public function __construct(
        public string $businessId,
        public string $name,
        public string $email,
    ) {}
}
