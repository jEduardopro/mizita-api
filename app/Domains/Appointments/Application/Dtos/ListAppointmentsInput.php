<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\InvalidCalendarRange;
use App\Domains\Appointments\ValueObjects\CalendarRange;

final readonly class ListAppointmentsInput
{
    public function __construct(
        public string $from,
        public string $to,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            from: self::textOrEmpty($payload['from'] ?? null),
            to: self::textOrEmpty($payload['to'] ?? null),
        );
    }

    /**
     * @throws InvalidCalendarRange
     */
    public function validate(): void
    {
        $this->toRange();
    }

    /**
     * @throws InvalidCalendarRange
     */
    public function toRange(): CalendarRange
    {
        return CalendarRange::fromStrings($this->from, $this->to);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
