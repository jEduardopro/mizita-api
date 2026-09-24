<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\Exceptions\InvalidTimeOfDay;
use App\Domains\Availability\Exceptions\InvalidWeekday;
use App\Domains\Availability\Exceptions\ScheduleNotSubmitted;
use App\Domains\Availability\ValueObjects\ScheduleInterval;

final readonly class ReplaceMyScheduleInput
{
    /**
     * @param  list<MyScheduleEntryInput>|null  $entries
     */
    public function __construct(
        public string $accountId,
        public ?array $entries,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        $schedule = $payload['schedule'] ?? null;

        return new self(
            accountId: $accountId,
            entries: is_array($schedule) ? self::entriesFrom($schedule) : null,
        );
    }

    /**
     * @throws ScheduleNotSubmitted
     * @throws InvalidWeekday
     * @throws InvalidTimeOfDay
     */
    public function validate(): void
    {
        $this->validateSchedule();
    }

    /**
     * @return list<ScheduleInterval>
     *
     * @throws InvalidWeekday
     * @throws InvalidTimeOfDay
     */
    public function intervals(): array
    {
        return array_map(
            static fn (MyScheduleEntryInput $entry): ScheduleInterval => $entry->toInterval(),
            $this->entries ?? [],
        );
    }

    /**
     * @throws ScheduleNotSubmitted
     * @throws InvalidWeekday
     * @throws InvalidTimeOfDay
     */
    private function validateSchedule(): void
    {
        if ($this->entries === null) {
            throw ScheduleNotSubmitted::forAccount($this->accountId);
        }

        foreach ($this->entries as $entry) {
            $entry->validate();
        }
    }

    /**
     * @param  array<mixed>  $schedule
     * @return list<MyScheduleEntryInput>
     */
    private static function entriesFrom(array $schedule): array
    {
        $entries = [];

        foreach ($schedule as $entry) {
            $parts = is_array($entry) ? $entry : [];

            $entries[] = new MyScheduleEntryInput(
                weekday: self::numberOrZero($parts['weekday'] ?? null),
                startsAt: self::textOrEmpty($parts['starts_at'] ?? null),
                endsAt: self::textOrEmpty($parts['ends_at'] ?? null),
            );
        }

        return $entries;
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function numberOrZero(mixed $value): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);

        return $number === false ? 0 : $number;
    }
}
