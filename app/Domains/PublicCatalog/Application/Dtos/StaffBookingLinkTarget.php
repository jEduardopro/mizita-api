<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

final readonly class StaffBookingLinkTarget
{
    public function __construct(
        public string $businessSlug,
        public string $staffMemberId,
        public ?string $serviceId,
    ) {}
}
