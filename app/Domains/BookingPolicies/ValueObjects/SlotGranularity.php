<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

use App\Domains\BookingPolicies\Exceptions\InvalidSlotGranularity;

final readonly class SlotGranularity
{
    public const MINIMUM_MINUTES = 5;

    public const MAXIMUM_MINUTES = 60;

    public const STEP_MINUTES = 5;

    private function __construct(
        public int $minutes,
    ) {}

    /**
     * @throws InvalidSlotGranularity
     */
    public static function ofMinutes(int $minutes): self
    {
        if ($minutes < self::MINIMUM_MINUTES) {
            throw InvalidSlotGranularity::tooShort($minutes);
        }

        if ($minutes > self::MAXIMUM_MINUTES) {
            throw InvalidSlotGranularity::tooLong($minutes);
        }

        if ($minutes % self::STEP_MINUTES !== 0) {
            throw InvalidSlotGranularity::notAMultiple($minutes, self::STEP_MINUTES);
        }

        return new self($minutes);
    }

    public static function restore(int $minutes): self
    {
        return new self($minutes);
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
