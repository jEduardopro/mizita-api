<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use App\Domains\Appointments\ValueObjects\ScheduleInstant;
use DateTimeImmutable;

final readonly class RescheduleGuestBookingInput
{
    public function __construct(
        public string $businessId,
        public GuestBookingCredentials $credentials,
        public string $startsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(
        array $payload,
        string $businessId,
        GuestBookingCredentials $credentials,
    ): self {
        return new self(
            businessId: $businessId,
            credentials: $credentials,
            startsAt: self::textOrEmpty($payload['starts_at'] ?? null),
        );
    }

    /**
     * @throws GuestBookingNotFound
     * @throws InvalidAppointmentSchedule
     */
    public function validate(): void
    {
        $this->credentials->validate();
        $this->validateStartsAt();
    }

    /**
     * @throws InvalidAppointmentSchedule
     */
    public function toStartsAt(): DateTimeImmutable
    {
        return ScheduleInstant::fromString($this->startsAt)->value;
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateStartsAt(): void
    {
        $this->toStartsAt();
    }
}
