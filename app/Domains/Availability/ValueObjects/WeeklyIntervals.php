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

    public function orInheritedFrom(self $wider): self
    {
        return $this->isEmpty() ? $wider : $this;
    }

    /**
     * @return list<array{int, int}>
     */
    public function forWeekday(Weekday $weekday): array
    {
        return $this->minutesByWeekday[$weekday->value] ?? [];
    }

    public function intersect(self $other): self
    {
        $intersected = [];

        foreach (Weekday::cases() as $weekday) {
            $shared = self::overlapping($this->forWeekday($weekday), $other->forWeekday($weekday));

            if ($shared === []) {
                continue;
            }

            $intersected[$weekday->value] = $shared;
        }

        return new self($intersected);
    }

    /**
     * @param  list<array{int, int}>  $ours
     * @param  list<array{int, int}>  $theirs
     * @return list<array{int, int}>
     */
    private static function overlapping(array $ours, array $theirs): array
    {
        $shared = [];
        $ourIndex = 0;
        $theirIndex = 0;
        $ourCount = count($ours);
        $theirCount = count($theirs);

        while ($ourIndex < $ourCount && $theirIndex < $theirCount) {
            [$ourStart, $ourEnd] = $ours[$ourIndex];
            [$theirStart, $theirEnd] = $theirs[$theirIndex];

            $start = max($ourStart, $theirStart);
            $end = min($ourEnd, $theirEnd);

            if ($start < $end) {
                $shared[] = [$start, $end];
            }

            if ($ourEnd < $theirEnd) {
                $ourIndex++;

                continue;
            }

            $theirIndex++;
        }

        return $shared;
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
