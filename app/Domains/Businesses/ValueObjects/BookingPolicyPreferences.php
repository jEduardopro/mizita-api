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

    public function changesAnythingOf(BookingPolicySnapshot $current): bool
    {
        return $this->leadTimeMinutes !== $current->leadTimeMinutes
            || $this->bookingWindowMinutes !== $current->bookingWindowMinutes
            || $this->slotGranularityMinutes !== $current->slotGranularityMinutes
            || $this->cancellationWindowMinutes !== $current->cancellationWindowMinutes
            || self::normalizedMessage($this->policyMessage) !== self::normalizedMessage($current->policyMessage)
            || $this->displayOnBookingPage !== $current->displayOnBookingPage;
    }

    private static function normalizedMessage(?string $message): ?string
    {
        $trimmed = trim($message ?? '');

        return $trimmed === '' ? null : $trimmed;
    }
}
