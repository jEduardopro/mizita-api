<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicBookingRequest
{
    public const MAXIMUM_NOTES_LENGTH = 2000;

    public function __construct(
        public string $serviceId,
        public string $staffMemberId,
        public string $startsAt,
        public PublicGuestDetails $guest,
        public ?string $notes,
    ) {}
}
