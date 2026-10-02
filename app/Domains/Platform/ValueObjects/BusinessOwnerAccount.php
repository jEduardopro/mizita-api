<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

final readonly class BusinessOwnerAccount
{
    public function __construct(
        public string $businessId,
        public string $businessName,
        public string $accountId,
        public string $ownerName,
    ) {}
}
