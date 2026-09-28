<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

final readonly class BookingReadiness
{
    /**
     * @param  list<BookingLinkBlocker>  $blockers
     */
    private function __construct(
        public array $blockers,
    ) {}

    public static function blockedBy(BookingLinkBlocker ...$blockers): self
    {
        return new self(array_values($blockers));
    }

    public function allowsBookingLink(): bool
    {
        return $this->blockers === [];
    }
}
