<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\ReferenceCodeFragment;
use App\Domains\Payments\ValueObjects\ReportPeriod;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Domains\Payments\ValueObjects\SaleSort;
use App\Domains\Payments\ValueObjects\SalesQuery;

final readonly class ListSalesInput
{
    public const MAXIMUM_REFERENCE_LENGTH = ReferenceCodeFragment::MAXIMUM_LENGTH;

    private const DEFAULT_SORT = SaleSort::CreatedAt;

    /**
     * @param  list<string>  $statuses
     */
    public function __construct(
        public PaymentReportCriteriaInput $criteria,
        public ?string $reference,
        public array $statuses,
        public ?string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            criteria: PaymentReportCriteriaInput::fromRequest($payload),
            reference: self::textOrNull($payload['reference'] ?? null),
            statuses: self::texts($payload['statuses'] ?? null),
            sort: self::textOrNull($payload['sort'] ?? null),
        );
    }

    /**
     * @throws InvalidPaymentReportPeriod
     * @throws InvalidPaymentReportFilter
     */
    public function validate(): void
    {
        $this->criteria->validate();
        $this->validateReference();
        $this->validateStatuses();
        $this->validateSort();
    }

    /**
     * @throws InvalidPaymentReportPeriod
     */
    public function period(): ?ReportPeriod
    {
        return $this->criteria->period();
    }

    /**
     * @throws InvalidPaymentReportFilter
     */
    public function toQuery(?ReportWindow $window): SalesQuery
    {
        return new SalesQuery(
            criteria: $this->criteria->toCriteria($window),
            reference: ReferenceCodeFragment::of($this->reference),
            statuses: array_map(
                static fn (string $status): PaymentStatus => PaymentStatus::from($status),
                array_values(array_unique($this->statuses)),
            ),
            sort: SaleSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
        );
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
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

    private function validateReference(): void
    {
        ReferenceCodeFragment::of($this->reference);
    }

    private function validateStatuses(): void
    {
        foreach ($this->statuses as $status) {
            if (PaymentStatus::tryFrom($status) === null) {
                throw InvalidPaymentReportFilter::unknownStatus($status);
            }
        }
    }

    private function validateSort(): void
    {
        if ($this->sort !== null && SaleSort::tryFrom($this->sort) === null) {
            throw InvalidPaymentReportFilter::unknownSort($this->sort);
        }
    }
}
