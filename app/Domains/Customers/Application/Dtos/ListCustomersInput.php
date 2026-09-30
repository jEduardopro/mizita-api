<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Exceptions\InvalidCustomerRegistrationPeriod;
use App\Domains\Customers\Exceptions\InvalidCustomerSearch;
use App\Domains\Customers\ValueObjects\CustomerQuery;
use App\Domains\Customers\ValueObjects\CustomerSort;
use App\Domains\Customers\ValueObjects\LocalDate;
use App\Domains\Customers\ValueObjects\RegistrationPeriod;
use App\Domains\Customers\ValueObjects\RegistrationWindow;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SearchTerm;
use App\Shared\ValueObjects\SortDirection;

final readonly class ListCustomersInput
{
    public const MAXIMUM_SEARCH_LENGTH = 120;

    private const DEFAULT_SORT = CustomerSort::Name;

    private const DEFAULT_DIRECTION = SortDirection::Ascending;

    public function __construct(
        public ?string $search,
        public ?string $sort,
        public ?string $direction,
        public ?int $page,
        public ?int $perPage,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            search: self::textOrNull($payload['search'] ?? null),
            sort: self::textOrNull($payload['sort'] ?? null),
            direction: self::textOrNull($payload['direction'] ?? null),
            page: self::countOrNull($payload['page'] ?? null),
            perPage: self::countOrNull($payload['per_page'] ?? null),
            createdFrom: self::filledTextOrNull($payload['created_from'] ?? null),
            createdTo: self::filledTextOrNull($payload['created_to'] ?? null),
        );
    }

    /**
     * @throws InvalidCustomerSearch
     * @throws InvalidCustomerRegistrationPeriod
     */
    public function validate(): void
    {
        $this->validateSearch();
        $this->validateRegistrationPeriod();
    }

    /**
     * @throws InvalidCustomerRegistrationPeriod
     */
    public function registrationPeriod(): ?RegistrationPeriod
    {
        if ($this->createdFrom === null && $this->createdTo === null) {
            return null;
        }

        if ($this->createdFrom === null || $this->createdTo === null) {
            throw InvalidCustomerRegistrationPeriod::incomplete();
        }

        return RegistrationPeriod::between(
            LocalDate::fromString($this->createdFrom),
            LocalDate::fromString($this->createdTo),
        );
    }

    /**
     * @param  list<string>  $phoneMatches
     */
    public function toQuery(array $phoneMatches = [], ?RegistrationWindow $registeredWithin = null): CustomerQuery
    {
        return new CustomerQuery(
            search: SearchTerm::of($this->search),
            sort: CustomerSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
            direction: SortDirection::tryFrom((string) $this->direction) ?? self::DEFAULT_DIRECTION,
            pagination: Pagination::of($this->page, $this->perPage),
            phoneMatches: $phoneMatches,
            registeredWithin: $registeredWithin,
        );
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function filledTextOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function countOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function validateSearch(): void
    {
        if ($this->search !== null && mb_strlen(trim($this->search)) > self::MAXIMUM_SEARCH_LENGTH) {
            throw InvalidCustomerSearch::tooLong(self::MAXIMUM_SEARCH_LENGTH);
        }
    }

    private function validateRegistrationPeriod(): void
    {
        $this->registrationPeriod();
    }
}
