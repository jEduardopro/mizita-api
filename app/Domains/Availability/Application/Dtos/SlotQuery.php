<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\Exceptions\AvailabilityRangeTooWide;
use App\Domains\Availability\Exceptions\InvalidAvailabilityRange;
use App\Domains\Availability\Exceptions\InvalidSlotQuery;
use App\Domains\Availability\ValueObjects\LocalDateRange;

final readonly class SlotQuery
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $serviceId,
        public string $staffId,
        public string $from,
        public string $to,
        public ?string $excludingAppointmentId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            serviceId: self::textOrEmpty($payload['service_id'] ?? null),
            staffId: self::textOrEmpty($payload['staff_id'] ?? null),
            from: self::textOrEmpty($payload['from'] ?? null),
            to: self::textOrEmpty($payload['to'] ?? null),
        );
    }

    /**
     * @throws InvalidSlotQuery
     * @throws InvalidAvailabilityRange
     * @throws AvailabilityRangeTooWide
     */
    public function validate(): void
    {
        $this->validateServiceId();
        $this->validateStaffId();
        $this->validateRange();
    }

    /**
     * @throws InvalidAvailabilityRange
     * @throws AvailabilityRangeTooWide
     */
    public function range(): LocalDateRange
    {
        return LocalDateRange::between($this->from, $this->to);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateServiceId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->serviceId) !== 1) {
            throw InvalidSlotQuery::malformedServiceId($this->serviceId);
        }
    }

    private function validateStaffId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->staffId) !== 1) {
            throw InvalidSlotQuery::malformedStaffId($this->staffId);
        }
    }

    private function validateRange(): void
    {
        $this->range();
    }
}
