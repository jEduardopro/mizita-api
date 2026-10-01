<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class SalesQuery
{
    /**
     * @param  list<PaymentStatus>  $statuses
     */
    public function __construct(
        public ReportCriteria $criteria,
        public ?ReferenceCodeFragment $reference,
        public array $statuses,
        public SaleSort $sort,
    ) {}
}
