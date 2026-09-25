<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\ValueObjects\CustomerAppointmentQuery;
use App\Domains\Appointments\ValueObjects\Identifier;
use App\Shared\ValueObjects\Pagination;

final readonly class ListCustomerAppointmentsInput
{
    public function __construct(
        public string $customerId,
        public ?int $page,
        public ?int $perPage,
        public string $accountId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $customerId, string $accountId): self
    {
        return new self(
            customerId: $customerId,
            page: self::countOrNull($payload['page'] ?? null),
            perPage: self::countOrNull($payload['per_page'] ?? null),
            accountId: $accountId,
        );
    }

    /**
     * @throws AppointmentCustomerNotFound
     * @throws CalendarNotAccessible
     */
    public function validate(): void
    {
        $this->validateCustomerId();
        $this->validateAccountId();
    }

    public function toQuery(): CustomerAppointmentQuery
    {
        return new CustomerAppointmentQuery(
            customerId: $this->customerId,
            pagination: Pagination::of($this->page, $this->perPage),
        );
    }

    private static function countOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function validateCustomerId(): void
    {
        if (! Identifier::isWellFormed($this->customerId)) {
            throw AppointmentCustomerNotFound::withId($this->customerId);
        }
    }

    private function validateAccountId(): void
    {
        if (! Identifier::isWellFormed($this->accountId)) {
            throw CalendarNotAccessible::forAccount($this->accountId);
        }
    }
}
