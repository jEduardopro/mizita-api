<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

use App\Domains\BookingPolicies\Exceptions\InvalidCancellationWindow;
use DateTimeImmutable;

final readonly class CancellationWindow
{
    public const MINIMUM_MINUTES = 0;

    public const MAXIMUM_MINUTES = 43200;

    private const SECONDS_PER_MINUTE = 60;

    private function __construct(
        public ?int $minutes,
    ) {}

    /**
     * @throws InvalidCancellationWindow
     */
    public static function ofMinutes(int $minutes): self
    {
        if ($minutes < self::MINIMUM_MINUTES) {
            throw InvalidCancellationWindow::negative($minutes);
        }

        if ($minutes > self::MAXIMUM_MINUTES) {
            throw InvalidCancellationWindow::tooLong($minutes);
        }

        return new self($minutes);
    }

    public static function notAllowed(): self
    {
        return new self(null);
    }

    public static function restore(?int $minutes): self
    {
        return new self($minutes);
    }

    public function isAllowed(): bool
    {
        return $this->minutes !== null;
    }

    public function allowsChangeAt(DateTimeImmutable $startsAt, DateTimeImmutable $now): bool
    {
        if ($this->minutes === null) {
            return false;
        }

        $remainingSeconds = $startsAt->getTimestamp() - $now->getTimestamp();

        return $remainingSeconds >= $this->minutes * self::SECONDS_PER_MINUTE;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
