<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

use App\Domains\Services\Exceptions\InvalidServiceDuration;

final readonly class Duration
{
    public const MINIMUM_MINUTES = 1;

    public const MAXIMUM_MINUTES = 1440;

    private function __construct(
        public int $minutes,
    ) {}

    /**
     * @throws InvalidServiceDuration
     */
    public static function ofMinutes(int $minutes): self
    {
        if ($minutes < self::MINIMUM_MINUTES) {
            throw InvalidServiceDuration::tooShort($minutes);
        }

        if ($minutes > self::MAXIMUM_MINUTES) {
            throw InvalidServiceDuration::tooLong($minutes);
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
