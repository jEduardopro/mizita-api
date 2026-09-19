<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

use App\Domains\BookingPolicies\Exceptions\InvalidBookingWindow;

final readonly class BookingWindow
{
    public const MINIMUM_MINUTES = 1;

    public const MAXIMUM_DAYS = 365;

    public const HARD_CAP_MINUTES = self::MAXIMUM_DAYS * self::MINUTES_PER_DAY;

    private const MINUTES_PER_DAY = 1440;

    private function __construct(
        private ?int $minutes,
    ) {}

    /**
     * @throws InvalidBookingWindow
     */
    public static function ofMinutes(int $minutes): self
    {
        if ($minutes < self::MINIMUM_MINUTES) {
            throw InvalidBookingWindow::notPositive($minutes);
        }

        if ($minutes > self::HARD_CAP_MINUTES) {
            throw InvalidBookingWindow::tooLong($minutes);
        }

        return new self($minutes);
    }

    public static function unlimited(): self
    {
        return new self(null);
    }

    public static function restore(?int $minutes): self
    {
        return new self($minutes);
    }

    public function isUnlimited(): bool
    {
        return $this->minutes === null;
    }

    public function minutes(): ?int
    {
        return $this->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
