<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\Exceptions\InvalidCalendarRange;
use App\Domains\Appointments\ValueObjects\CalendarRange;
use App\Domains\Appointments\ValueObjects\Identifier;

final readonly class ListAppointmentsInput
{
    public function __construct(
        public string $from,
        public string $to,
        public string $accountId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $accountId): self
    {
        return new self(
            from: self::textOrEmpty($payload['from'] ?? null),
            to: self::textOrEmpty($payload['to'] ?? null),
            accountId: $accountId,
        );
    }

    /**
     * @throws InvalidCalendarRange
     * @throws CalendarNotAccessible
     */
    public function validate(): void
    {
        $this->toRange();
        $this->validateAccountId();
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

    private function validateAccountId(): void
    {
        if (! Identifier::isWellFormed($this->accountId)) {
            throw CalendarNotAccessible::forAccount($this->accountId);
        }
    }
}
