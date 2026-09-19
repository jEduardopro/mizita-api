<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use DateTimeImmutable;

final readonly class PublicGuestBooking
{
    public function __construct(
        public string $referenceCode,
        public string $customerName,
        public string $serviceName,
        public string $staffMemberName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $durationMinutes,
        public PublicBookingStatus $status,
        public ?DateTimeImmutable $cancelledAt,
        public ?int $cancellationWindowMinutes,
        public bool $changeable,
    ) {}
}
