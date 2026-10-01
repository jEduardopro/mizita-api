<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPaymentReportFilter extends DomainException implements DomainFailure
{
    public static function malformedCustomer(string $customerId): self
    {
        return new self("The customer filter [{$customerId}] is not a well-formed identifier.");
    }

    public static function tooManyCustomers(int $maximum): self
    {
        return new self("A payment report may filter by at most {$maximum} customers.");
    }

    public static function unknownStatus(string $status): self
    {
        return new self("The payment status [{$status}] is not one a sale can have.");
    }

    public static function unknownTransactionType(string $type): self
    {
        return new self("The transaction type [{$type}] is not one a transaction can have.");
    }

    public static function unknownPaymentMethod(string $code): self
    {
        return new self("The payment method [{$code}] is not in the catalog.");
    }

    public static function referenceTooLong(int $maximum): self
    {
        return new self("A booking reference filter may not run past {$maximum} characters.");
    }

    public static function unknownSort(string $sort): self
    {
        return new self("A payment report cannot be sorted by [{$sort}].");
    }

    public static function unknownDirection(string $direction): self
    {
        return new self("The sort direction [{$direction}] is neither ascending nor descending.");
    }

    public static function pageOutOfRange(int $page): self
    {
        return new self("The page [{$page}] is not a page a report can have.");
    }

    public static function perPageOutOfRange(int $perPage, int $maximum): self
    {
        return new self("A report page holds between 1 and {$maximum} rows, got [{$perPage}].");
    }

    public function errorCode(): string
    {
        return 'invalid_payment_report_filter';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
