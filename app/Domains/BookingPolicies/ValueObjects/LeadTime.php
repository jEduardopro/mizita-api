<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

use App\Domains\BookingPolicies\Exceptions\InvalidLeadTime;

final readonly class LeadTime
{
    public const MINIMUM_MINUTES = 0;

    public const MAXIMUM_MINUTES = 43200;

    private function __construct(
        public int $minutes,
    ) {}

    /**
     * @throws InvalidLeadTime
     */
    public static function ofMinutes(int $minutes): self
    {
        if ($minutes < self::MINIMUM_MINUTES) {
            throw InvalidLeadTime::negative($minutes);
        }

        if ($minutes > self::MAXIMUM_MINUTES) {
            throw InvalidLeadTime::tooLong($minutes);
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
