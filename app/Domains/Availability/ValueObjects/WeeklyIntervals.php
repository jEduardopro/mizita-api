<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Entities\ScheduleRule;

final readonly class WeeklyIntervals
{
    /**
     * @param  array<int, list<array{int, int}>>  $minutesByWeekday
     */
    private function __construct(private array $minutesByWeekday) {}

    /**
     * @param  list<ScheduleRule>  $rules
     */
    public static function fromRules(array $rules, ScheduleOwnerType $ownerType, string $ownerId): self
    {
        $minutesByWeekday = [];

        foreach ($rules as $rule) {
            if ($rule->ownerType !== $ownerType || $rule->ownerId !== $ownerId) {
                continue;
            }

            $minutesByWeekday[$rule->weekday->value][] = [
                $rule->startsAt()->minutesFromMidnight(),
                $rule->endsAt()->minutesFromMidnight(),
            ];
        }

        return new self(self::sortedByWeekday($minutesByWeekday));
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->minutesByWeekday === [];
    }

    public function orInheritedFrom(self $inherited): self
    {
        return $this->isEmpty() ? $inherited : $this;
    }

    /**
     * @return list<array{int, int}>
     */
    public function forWeekday(Weekday $weekday): array
    {
        return $this->minutesByWeekday[$weekday->value] ?? [];
    }

    /**
     * @param  array<int, list<array{int, int}>>  $minutesByWeekday
     * @return array<int, list<array{int, int}>>
     */
    private static function sortedByWeekday(array $minutesByWeekday): array
    {
        $sorted = [];

        foreach ($minutesByWeekday as $weekday => $intervals) {
            usort($intervals, static fn (array $one, array $other): int => $one[0] <=> $other[0]);

            $sorted[$weekday] = array_values($intervals);
        }

        return $sorted;
    }
}
