<?php

declare(strict_types=1);

namespace Tests\Support\Availability;

use App\Domains\Availability\Application\Dtos\ReplaceScheduleInput;
use App\Domains\Availability\Entities\ScheduleRule;
use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class ScheduleFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const RULE_ID = '01930000-0000-7000-8000-0000000000f1';

    public const SECOND_RULE_ID = '01930000-0000-7000-8000-0000000000f2';

    public const THIRD_RULE_ID = '01930000-0000-7000-8000-0000000000f3';

    public const GENERATED_RULE_ID = '01930000-0000-7000-8000-0000000000f9';

    public const STAFF_ID = '01930000-0000-7000-8000-0000000000d1';

    public const OTHER_STAFF_ID = '01930000-0000-7000-8000-0000000000d2';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const MORNING_START = '09:00';

    public const MORNING_END = '14:00';

    public const AFTERNOON_START = '14:00';

    public const AFTERNOON_END = '18:00';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function rule(
        string $id = self::RULE_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        ScheduleOwnerType $ownerType = ScheduleOwnerType::Business,
        ?string $ownerId = null,
        Weekday $weekday = Weekday::Monday,
        string $startsAt = self::MORNING_START,
        string $endsAt = self::MORNING_END,
        ?DateTimeImmutable $now = null,
    ): ScheduleRule {
        return ScheduleRule::create(
            id: $id,
            businessId: $businessId,
            ownerType: $ownerType,
            ownerId: $ownerId ?? FakeBusinessContext::BUSINESS_ID,
            weekday: $weekday,
            startsAt: TimeOfDay::fromString($startsAt),
            endsAt: TimeOfDay::fromString($endsAt),
            now: $now ?? self::now(),
        );
    }

    /**
     * @param  array<int, list<array{string, string}>>  $intervalsByWeekday
     */
    public static function weeklyIntervals(
        array $intervalsByWeekday,
        ScheduleOwnerType $ownerType = ScheduleOwnerType::Business,
        ?string $ownerId = null,
    ): WeeklyIntervals {
        $owner = $ownerId ?? FakeBusinessContext::BUSINESS_ID;
        $rules = [];
        $sequence = 0;

        foreach ($intervalsByWeekday as $weekday => $intervals) {
            foreach ($intervals as [$startsAt, $endsAt]) {
                $rules[] = self::rule(
                    id: sprintf('01930000-0000-7000-8000-0000000%05d', $sequence),
                    ownerType: $ownerType,
                    ownerId: $owner,
                    weekday: Weekday::fromNumber($weekday),
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                );

                $sequence++;
            }
        }

        return WeeklyIntervals::fromRules($rules, $ownerType, $owner);
    }

    public static function interval(
        Weekday $weekday = Weekday::Monday,
        string $startsAt = self::MORNING_START,
        string $endsAt = self::MORNING_END,
    ): ScheduleInterval {
        return new ScheduleInterval(
            weekday: $weekday,
            startsAt: TimeOfDay::fromString($startsAt),
            endsAt: TimeOfDay::fromString($endsAt),
        );
    }

    /**
     * @param  list<ScheduleInterval>|null  $intervals
     */
    public static function input(
        ScheduleOwnerType $ownerType = ScheduleOwnerType::Business,
        ?string $ownerId = null,
        ?array $intervals = null,
    ): ReplaceScheduleInput {
        return new ReplaceScheduleInput(
            ownerType: $ownerType,
            ownerId: $ownerId ?? FakeBusinessContext::BUSINESS_ID,
            intervals: $intervals ?? [self::interval()],
        );
    }
}
