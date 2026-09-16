<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Exceptions\InvalidTimeOfDay;

final readonly class TimeOfDay
{
    public const MINUTES_PER_HOUR = 60;

    public const HOURS_PER_DAY = 24;

    private const SHAPE = '/^(?<hour>\d{1,2}):(?<minute>\d{2})(?::\d{2})?$/D';

    private const SEPARATOR = ':';

    private const PAD_LENGTH = 2;

    private function __construct(
        public int $hour,
        public int $minute,
    ) {}

    /**
     * @throws InvalidTimeOfDay
     */
    public static function fromString(string $value): self
    {
        $candidate = trim($value);

        if (preg_match(self::SHAPE, $candidate, $matches) !== 1) {
            throw InvalidTimeOfDay::malformed($value);
        }

        $hour = (int) $matches['hour'];
        $minute = (int) $matches['minute'];

        if ($hour >= self::HOURS_PER_DAY || $minute >= self::MINUTES_PER_HOUR) {
            throw InvalidTimeOfDay::outOfRange($value);
        }

        return new self($hour, $minute);
    }

    public static function restore(int $hour, int $minute): self
    {
        return new self($hour, $minute);
    }

    public function minutesFromMidnight(): int
    {
        return $this->hour * self::MINUTES_PER_HOUR + $this->minute;
    }

    public function isBefore(self $other): bool
    {
        return $this->minutesFromMidnight() < $other->minutesFromMidnight();
    }

    public function equals(self $other): bool
    {
        return $this->minutesFromMidnight() === $other->minutesFromMidnight();
    }

    public function toString(): string
    {
        return str_pad((string) $this->hour, self::PAD_LENGTH, '0', STR_PAD_LEFT)
            .self::SEPARATOR
            .str_pad((string) $this->minute, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }
}
