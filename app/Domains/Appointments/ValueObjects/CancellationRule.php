<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use DateTimeImmutable;

final readonly class CancellationRule
{
    private const SECONDS_PER_MINUTE = 60;

    private function __construct(
        public ?int $minutes,
    ) {}

    public static function ofMinutes(int $minutes): self
    {
        return new self($minutes);
    }

    public static function notAllowed(): self
    {
        return new self(null);
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
}
