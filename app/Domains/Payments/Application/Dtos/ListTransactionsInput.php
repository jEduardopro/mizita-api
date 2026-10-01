<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\ReportPeriod;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Domains\Payments\ValueObjects\TransactionSort;
use App\Domains\Payments\ValueObjects\TransactionsQuery;

final readonly class ListTransactionsInput
{
    private const DEFAULT_SORT = TransactionSort::ProcessedAt;

    /**
     * @param  list<string>  $types
     * @param  list<string>  $methods
     */
    public function __construct(
        public PaymentReportCriteriaInput $criteria,
        public array $types,
        public array $methods,
        public ?string $sort,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            criteria: PaymentReportCriteriaInput::fromRequest($payload),
            types: self::texts($payload['types'] ?? null),
            methods: self::texts($payload['methods'] ?? null),
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
        $this->validateTypes();
        $this->validateMethods();
        $this->validateSort();
    }

    /**
     * @throws InvalidPaymentReportPeriod
     */
    public function period(): ?ReportPeriod
    {
        return $this->criteria->period();
    }

    public function toQuery(?ReportWindow $window): TransactionsQuery
    {
        return new TransactionsQuery(
            criteria: $this->criteria->toCriteria($window),
            types: array_map(
                static fn (string $type): PaymentTransactionType => PaymentTransactionType::from($type),
                array_values(array_unique($this->types)),
            ),
            methods: array_map(
                static fn (string $method): PaymentMethodCode => PaymentMethodCode::from($method),
                array_values(array_unique($this->methods)),
            ),
            sort: TransactionSort::tryFrom((string) $this->sort) ?? self::DEFAULT_SORT,
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

    private function validateTypes(): void
    {
        foreach ($this->types as $type) {
            if (PaymentTransactionType::tryFrom($type) === null) {
                throw InvalidPaymentReportFilter::unknownTransactionType($type);
            }
        }
    }

    private function validateMethods(): void
    {
        foreach ($this->methods as $method) {
            if (PaymentMethodCode::tryFrom($method) === null) {
                throw InvalidPaymentReportFilter::unknownPaymentMethod($method);
            }
        }
    }

    private function validateSort(): void
    {
        if ($this->sort !== null && TransactionSort::tryFrom($this->sort) === null) {
            throw InvalidPaymentReportFilter::unknownSort($this->sort);
        }
    }
}
