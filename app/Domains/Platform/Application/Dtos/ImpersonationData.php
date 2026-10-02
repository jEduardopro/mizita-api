<?php

declare(strict_types=1);

namespace App\Domains\Platform\Application\Dtos;

use App\Domains\Platform\ValueObjects\Impersonation;
use DateTimeImmutable;

final readonly class ImpersonationData
{
    public function __construct(
        public string $businessId,
        public string $businessName,
        public string $ownerName,
        public DateTimeImmutable $expiresAt,
    ) {}

    public static function of(Impersonation $impersonation): self
    {
        return new self(
            businessId: $impersonation->businessId,
            businessName: $impersonation->businessName,
            ownerName: $impersonation->ownerName,
            expiresAt: $impersonation->expiresAt,
        );
    }
}
