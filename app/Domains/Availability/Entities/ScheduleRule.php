<?php

declare(strict_types=1);

namespace App\Domains\Availability\Entities;

use App\Domains\Availability\Exceptions\ScheduleIntervalInverted;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use DateTimeImmutable;

final class ScheduleRule
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly ScheduleOwnerType $ownerType,
        public readonly string $ownerId,
        public readonly Weekday $weekday,
        private TimeOfDay $startsAt,
        private TimeOfDay $endsAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws ScheduleIntervalInverted
     */
    public static function create(
        string $id,
        string $businessId,
        ScheduleOwnerType $ownerType,
        string $ownerId,
        Weekday $weekday,
        TimeOfDay $startsAt,
        TimeOfDay $endsAt,
        DateTimeImmutable $now,
    ): self {
        self::refuseInverted($startsAt, $endsAt);

        return new self(
            id: $id,
            businessId: $businessId,
            ownerType: $ownerType,
            ownerId: $ownerId,
            weekday: $weekday,
            startsAt: $startsAt,
            endsAt: $endsAt,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        ScheduleOwnerType $ownerType,
        string $ownerId,
        Weekday $weekday,
        TimeOfDay $startsAt,
        TimeOfDay $endsAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            ownerType: $ownerType,
            ownerId: $ownerId,
            weekday: $weekday,
            startsAt: $startsAt,
            endsAt: $endsAt,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws ScheduleIntervalInverted
     */
    public function reschedule(TimeOfDay $startsAt, TimeOfDay $endsAt): void
    {
        self::refuseInverted($startsAt, $endsAt);

        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
    }

    public function startsAt(): TimeOfDay
    {
        return $this->startsAt;
    }

    public function endsAt(): TimeOfDay
    {
        return $this->endsAt;
    }

    public function overlaps(self $other): bool
    {
        if ($this->weekday !== $other->weekday) {
            return false;
        }

        return $this->startsAt->minutesFromMidnight() < $other->endsAt->minutesFromMidnight()
            && $other->startsAt->minutesFromMidnight() < $this->endsAt->minutesFromMidnight();
    }

    /**
     * @throws ScheduleIntervalInverted
     */
    private static function refuseInverted(TimeOfDay $startsAt, TimeOfDay $endsAt): void
    {
        if (! $startsAt->isBefore($endsAt)) {
            throw ScheduleIntervalInverted::between($startsAt->toString(), $endsAt->toString());
        }
    }
}
