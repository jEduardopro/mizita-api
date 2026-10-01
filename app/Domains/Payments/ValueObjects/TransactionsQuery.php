<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class TransactionsQuery
{
    /**
     * @param  list<PaymentTransactionType>  $types
     * @param  list<PaymentMethodCode>  $methods
     */
    public function __construct(
        public ReportCriteria $criteria,
        public array $types,
        public array $methods,
        public TransactionSort $sort,
    ) {}
}
