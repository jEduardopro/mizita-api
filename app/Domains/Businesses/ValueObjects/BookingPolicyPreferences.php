<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

final readonly class BookingPolicyPreferences
{
    public function __construct(
        public int $leadTimeMinutes,
        public ?int $bookingWindowMinutes,
        public int $slotGranularityMinutes,
        public ?int $cancellationWindowMinutes,
        public ?string $policyMessage,
        public bool $displayOnBookingPage,
    ) {}
}
