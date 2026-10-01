<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\ValueObjects\SaleRecord;
use App\Domains\Payments\ValueObjects\SalesQuery;
use App\Domains\Payments\ValueObjects\TransactionRecord;
use App\Domains\Payments\ValueObjects\TransactionsQuery;
use App\Shared\ValueObjects\Paginated;

interface PaymentReports
{
    /**
     * @return Paginated<SaleRecord>
     */
    public function salesPage(string $businessId, SalesQuery $query): Paginated;

    /**
     * @return Paginated<TransactionRecord>
     */
    public function transactionsPage(string $businessId, TransactionsQuery $query): Paginated;
}
