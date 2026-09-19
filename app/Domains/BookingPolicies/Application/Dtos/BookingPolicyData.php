<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Application\Dtos;

final readonly class BookingPolicyData
{
    public function __construct(
        public string $id,
        public int $leadTimeMinutes,
        public ?int $bookingWindowMinutes,
        public bool $bookingWindowUnlimited,
        public int $slotGranularityMinutes,
        public ?int $cancellationWindowMinutes,
        public bool $cancellationAllowed,
        public ?string $policyMessage,
        public bool $displayOnBookingPage,
    ) {}
}
