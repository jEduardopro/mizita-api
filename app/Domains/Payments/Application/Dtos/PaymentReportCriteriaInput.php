<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\Identifier;
use App\Domains\Payments\ValueObjects\LocalDate;
use App\Domains\Payments\ValueObjects\ReportCriteria;
use App\Domains\Payments\ValueObjects\ReportPeriod;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;

final readonly class PaymentReportCriteriaInput
{
    public const MAXIMUM_CUSTOMER_FILTER_SIZE = 100;

    public const FIRST_PAGE = 1;

    public const MINIMUM_PER_PAGE = 1;

    private const DEFAULT_DIRECTION = SortDirection::Descending;

    /**
     * @param  list<string>  $customerIds
     */
    public function __construct(
        public ?string $from,
        public ?string $to,
        public array $customerIds,
        public ?string $direction,
        public ?int $page,
        public ?int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            from: self::filledTextOrNull($payload['from'] ?? null),
            to: self::filledTextOrNull($payload['to'] ?? null),
            customerIds: self::texts($payload['customer_ids'] ?? null),
            direction: self::textOrNull($payload['direction'] ?? null),
            page: self::countOrNull($payload['page'] ?? null),
            perPage: self::countOrNull($payload['per_page'] ?? null),
        );
    }

    /**
     * @throws InvalidPaymentReportPeriod
     * @throws InvalidPaymentReportFilter
     */
    public function validate(): void
    {
        $this->validatePeriod();
        $this->validateCustomerIds();
        $this->validateDirection();
        $this->validatePage();
        $this->validatePerPage();
    }

    /**
     * @throws InvalidPaymentReportPeriod
     */
    public function period(): ?ReportPeriod
    {
        if ($this->from === null && $this->to === null) {
            return null;
        }

        if ($this->from === null || $this->to === null) {
            throw InvalidPaymentReportPeriod::incomplete();
        }

        return ReportPeriod::between(LocalDate::fromString($this->from), LocalDate::fromString($this->to));
    }

    public function toCriteria(?ReportWindow $window): ReportCriteria
    {
        return new ReportCriteria(
            window: $window,
            customerIds: array_values(array_unique($this->customerIds)),
            direction: SortDirection::tryFrom((string) $this->direction) ?? self::DEFAULT_DIRECTION,
            pagination: Pagination::of($this->page, $this->perPage),
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

    /**
     * @return list<string>
     */
    private static function texts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $text): string => is_string($text) ? $text : '',
            $value,
        ));
    }

    private function validatePeriod(): void
    {
        $this->period();
    }

    private function validateCustomerIds(): void
    {
        if (count($this->customerIds) > self::MAXIMUM_CUSTOMER_FILTER_SIZE) {
            throw InvalidPaymentReportFilter::tooManyCustomers(self::MAXIMUM_CUSTOMER_FILTER_SIZE);
        }

        foreach ($this->customerIds as $customerId) {
            if (! Identifier::isWellFormed($customerId)) {
                throw InvalidPaymentReportFilter::malformedCustomer($customerId);
            }
        }
    }

    private function validateDirection(): void
    {
        if ($this->direction !== null && SortDirection::tryFrom($this->direction) === null) {
            throw InvalidPaymentReportFilter::unknownDirection($this->direction);
        }
    }

    private function validatePage(): void
    {
        if ($this->page !== null && ($this->page < self::FIRST_PAGE || $this->page > Pagination::MAXIMUM_PAGE)) {
            throw InvalidPaymentReportFilter::pageOutOfRange($this->page);
        }
    }

    private function validatePerPage(): void
    {
        if ($this->perPage === null) {
            return;
        }

        if ($this->perPage < self::MINIMUM_PER_PAGE || $this->perPage > Pagination::MAXIMUM_PER_PAGE) {
            throw InvalidPaymentReportFilter::perPageOutOfRange($this->perPage, Pagination::MAXIMUM_PER_PAGE);
        }
    }
}
