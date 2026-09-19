<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicBookingCredentials
{
    public function __construct(
        public string $referenceCode,
        public string $manageToken,
    ) {}
}
