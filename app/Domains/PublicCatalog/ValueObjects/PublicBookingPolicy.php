<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicBookingPolicy
{
    public function __construct(
        public string $policyMessage,
    ) {}
}
