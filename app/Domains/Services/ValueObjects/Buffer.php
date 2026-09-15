<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

use App\Domains\Services\Exceptions\InvalidServiceBuffer;

final readonly class Buffer
{
    public const MINIMUM_MINUTES = 0;

    public const MAXIMUM_MINUTES = 1440;

    private function __construct(
        public int $minutes,
    ) {}

    /**
     * @throws InvalidServiceBuffer
     */
    public static function ofMinutes(int $minutes): self
    {
        if ($minutes < self::MINIMUM_MINUTES) {
            throw InvalidServiceBuffer::negative($minutes);
        }

        if ($minutes > self::MAXIMUM_MINUTES) {
            throw InvalidServiceBuffer::tooLong($minutes);
        }

        return new self($minutes);
    }

    public static function none(): self
    {
        return new self(self::MINIMUM_MINUTES);
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
