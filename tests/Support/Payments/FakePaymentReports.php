<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\PaymentReports;
use App\Domains\Payments\ValueObjects\SaleRecord;
use App\Domains\Payments\ValueObjects\SalesQuery;
use App\Domains\Payments\ValueObjects\TransactionRecord;
use App\Domains\Payments\ValueObjects\TransactionsQuery;
use App\Shared\ValueObjects\Paginated;
use Throwable;

final class FakePaymentReports implements PaymentReports
{
    /**
     * @var Paginated<SaleRecord>|null
     */
    private ?Paginated $sales = null;

    /**
     * @var Paginated<TransactionRecord>|null
     */
    private ?Paginated $transactions = null;

    private ?Throwable $failure = null;

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    /**
     * @var list<SalesQuery>
     */
    public array $salesQueries = [];

    /**
     * @var list<TransactionsQuery>
     */
    public array $transactionsQueries = [];

    /**
     * @param  Paginated<SaleRecord>  $page
     */
    public function returningSales(Paginated $page): self
    {
        $this->sales = $page;

        return $this;
    }

    /**
     * @param  Paginated<TransactionRecord>  $page
     */
    public function returningTransactions(Paginated $page): self
    {
        $this->transactions = $page;

        return $this;
    }

    public function failingWith(Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    /**
     * @return Paginated<SaleRecord>
     */
    public function salesPage(string $businessId, SalesQuery $query): Paginated
    {
        $this->businessIdsSeen[] = $businessId;
        $this->salesQueries[] = $query;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->sales ?? Paginated::of([], 0, $query->criteria->pagination);
    }

    /**
     * @return Paginated<TransactionRecord>
     */
    public function transactionsPage(string $businessId, TransactionsQuery $query): Paginated
    {
        $this->businessIdsSeen[] = $businessId;
        $this->transactionsQueries[] = $query;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->transactions ?? Paginated::of([], 0, $query->criteria->pagination);
    }
}
