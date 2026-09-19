<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Exceptions\InvalidBookingBlock;

final readonly class BookingBlock
{
    public const MINIMUM_DURATION_MINUTES = 1;

    public const MINIMUM_BUFFER_MINUTES = 0;

    private function __construct(
        public int $bufferBeforeMinutes,
        public int $durationMinutes,
        public int $bufferAfterMinutes,
    ) {}

    /**
     * @throws InvalidBookingBlock
     */
    public static function lasting(
        int $durationMinutes,
        int $bufferBeforeMinutes,
        int $bufferAfterMinutes,
    ): self {
        if ($durationMinutes < self::MINIMUM_DURATION_MINUTES) {
            throw InvalidBookingBlock::notPositive($durationMinutes);
        }

        if ($bufferBeforeMinutes < self::MINIMUM_BUFFER_MINUTES) {
            throw InvalidBookingBlock::negativeBuffer($bufferBeforeMinutes);
        }

        if ($bufferAfterMinutes < self::MINIMUM_BUFFER_MINUTES) {
            throw InvalidBookingBlock::negativeBuffer($bufferAfterMinutes);
        }

        return new self($bufferBeforeMinutes, $durationMinutes, $bufferAfterMinutes);
    }

    public function totalMinutes(): int
    {
        return $this->bufferBeforeMinutes + $this->durationMinutes + $this->bufferAfterMinutes;
    }

    public function minutesAfterStart(): int
    {
        return $this->durationMinutes + $this->bufferAfterMinutes;
    }
}
