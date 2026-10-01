<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Eloquent;

use App\Domains\Payments\Contracts\PaymentReports;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\ReferenceCodeFragment;
use App\Domains\Payments\ValueObjects\ReportCriteria;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Domains\Payments\ValueObjects\SaleRecord;
use App\Domains\Payments\ValueObjects\SaleSort;
use App\Domains\Payments\ValueObjects\SalesQuery;
use App\Domains\Payments\ValueObjects\TransactionRecord;
use App\Domains\Payments\ValueObjects\TransactionSort;
use App\Domains\Payments\ValueObjects\TransactionsQuery;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\ValueObjects\Paginated;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentPaymentReports implements PaymentReports
{
    private const PAYMENTS_TABLE = 'payments';

    private const TRANSACTIONS_TABLE = 'payment_transactions';

    private const PAYMENT_METHODS_TABLE = 'payment_methods';

    private const APPOINTMENTS_TABLE = 'appointments';

    private const CUSTOMERS_TABLE = 'customers';

    private const STORAGE_TIMEZONE = 'UTC';

    private const NOTHING_PAID = 0;

    /**
     * @var list<string>
     */
    private const LIKE_METACHARACTERS = ['\\', '%', '_'];

    /**
     * @var list<string>
     */
    private const ESCAPED_LIKE_METACHARACTERS = ['\\\\', '\\%', '\\_'];

    /**
     * @var list<string>
     */
    private const SALE_COLUMNS = [
        'payments.uuid as id',
        'payments.created_at as created_at',
        'payments.total_cents as total_cents',
        'payments.paid_cents as paid_cents',
        'payments.currency_code as currency_code',
        'appointments.reference_code as reference_code',
        'customers.uuid as customer_id',
        'customers.name as customer_name',
    ];

    /**
     * @var list<string>
     */
    private const TRANSACTION_COLUMNS = [
        'payment_transactions.uuid as id',
        'payment_transactions.processed_at as processed_at',
        'payment_transactions.type as type',
        'payment_transactions.total_cents as total_cents',
        'payments.currency_code as currency_code',
        'payment_methods.code as payment_method_code',
        'customers.uuid as customer_id',
        'customers.name as customer_name',
    ];

    public function __construct(
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    /**
     * @return Paginated<SaleRecord>
     */
    public function salesPage(string $businessId, SalesQuery $query): Paginated
    {
        $criteria = $query->criteria;
        $matching = self::matchingSales($this->businessKeys->teamKeyFor($businessId), $query);
        $total = $matching->count();

        $rows = $matching
            ->select(self::SALE_COLUMNS)
            ->orderBy(self::saleSortColumn($query->sort), $criteria->direction->value)
            ->orderBy('payments.id')
            ->offset($criteria->pagination->offset())
            ->limit($criteria->pagination->perPage)
            ->get();

        return self::pageOf($rows->all(), $total, $criteria, self::saleFrom(...));
    }

    /**
     * @return Paginated<TransactionRecord>
     */
    public function transactionsPage(string $businessId, TransactionsQuery $query): Paginated
    {
        $criteria = $query->criteria;
        $matching = self::matchingTransactions($this->businessKeys->teamKeyFor($businessId), $query);
        $total = $matching->count();

        $rows = self::sortTransactions($matching, $query)
            ->select(self::TRANSACTION_COLUMNS)
            ->orderBy('payment_transactions.id')
            ->offset($criteria->pagination->offset())
            ->limit($criteria->pagination->perPage)
            ->get();

        return self::pageOf($rows->all(), $total, $criteria, self::transactionFrom(...));
    }

    private static function matchingSales(int $businessKey, SalesQuery $query): Builder
    {
        $sales = self::paymentsOf($businessKey);

        self::filterByWindow($sales, 'payments.created_at', $query->criteria->window);
        self::filterByCustomers($sales, $businessKey, $query->criteria->customerIds);
        self::filterByReference($sales, $query->reference);
        self::filterByStatuses($sales, $query->statuses);

        return $sales;
    }

    private static function matchingTransactions(int $businessKey, TransactionsQuery $query): Builder
    {
        $transactions = self::paymentsOf($businessKey)
            ->join(self::TRANSACTIONS_TABLE, 'payment_transactions.payment_id', '=', 'payments.id')
            ->join(self::PAYMENT_METHODS_TABLE, 'payment_methods.id', '=', 'payment_transactions.payment_method_id')
            ->whereNull('payment_transactions.deleted_at');

        self::filterByWindow($transactions, 'payment_transactions.processed_at', $query->criteria->window);
        self::filterByCustomers($transactions, $businessKey, $query->criteria->customerIds);
        self::filterByTypes($transactions, $query->types);
        self::filterByMethods($transactions, $query->methods);

        return $transactions;
    }

    private static function paymentsOf(int $businessKey): Builder
    {
        return DB::table(self::PAYMENTS_TABLE)
            ->join(self::APPOINTMENTS_TABLE, 'appointments.id', '=', 'payments.appointment_id')
            ->join(self::CUSTOMERS_TABLE, 'customers.id', '=', 'appointments.customer_id')
            ->where('payments.business_id', $businessKey)
            ->whereNull('payments.deleted_at');
    }

    private static function sortTransactions(Builder $transactions, TransactionsQuery $query): Builder
    {
        $direction = $query->criteria->direction->value;

        return match ($query->sort) {
            TransactionSort::ProcessedAt => $transactions->orderBy('payment_transactions.processed_at', $direction),
            TransactionSort::Amount => $transactions->orderByRaw(self::signedAmount().' '.$direction),
        };
    }

    private static function filterByWindow(Builder $query, string $column, ?ReportWindow $window): void
    {
        if ($window === null) {
            return;
        }

        $query->where($column, '>=', self::storedInstant($window->startsAt))
            ->where($column, '<', self::storedInstant($window->endsAt));
    }

    /**
     * @param  list<string>  $customerIds
     */
    private static function filterByCustomers(Builder $query, int $businessKey, array $customerIds): void
    {
        if ($customerIds === []) {
            return;
        }

        $query->whereIn('appointments.customer_id', self::customerKeysOf($businessKey, $customerIds));
    }

    private static function filterByReference(Builder $query, ?ReferenceCodeFragment $reference): void
    {
        if ($reference === null) {
            return;
        }

        $query->where('appointments.reference_code', 'like', '%'.self::escapeLike($reference->value).'%');
    }

    /**
     * @param  list<PaymentStatus>  $statuses
     */
    private static function filterByStatuses(Builder $query, array $statuses): void
    {
        if ($statuses === []) {
            return;
        }

        $query->where(static function (Builder $matches) use ($statuses): void {
            foreach ($statuses as $status) {
                $matches->orWhere(static fn (Builder $inStatus): Builder => self::inStatus($inStatus, $status));
            }
        });
    }

    private static function inStatus(Builder $query, PaymentStatus $status): Builder
    {
        return match ($status) {
            PaymentStatus::Paid => $query->whereColumn('payments.paid_cents', '>=', 'payments.total_cents'),
            PaymentStatus::Pending => $query
                ->whereColumn('payments.paid_cents', '<', 'payments.total_cents')
                ->where('payments.paid_cents', self::NOTHING_PAID),
            PaymentStatus::PartiallyPaid => $query
                ->whereColumn('payments.paid_cents', '<', 'payments.total_cents')
                ->where('payments.paid_cents', '>', self::NOTHING_PAID),
        };
    }

    /**
     * @param  list<PaymentTransactionType>  $types
     */
    private static function filterByTypes(Builder $query, array $types): void
    {
        if ($types === []) {
            return;
        }

        $query->whereIn(
            'payment_transactions.type',
            array_map(static fn (PaymentTransactionType $type): string => $type->value, $types),
        );
    }

    /**
     * @param  list<PaymentMethodCode>  $methods
     */
    private static function filterByMethods(Builder $query, array $methods): void
    {
        if ($methods === []) {
            return;
        }

        $query->whereIn(
            'payment_methods.code',
            array_map(static fn (PaymentMethodCode $method): string => $method->value, $methods),
        );
    }

    /**
     * @param  list<string>  $customerIds
     * @return list<int>
     */
    private static function customerKeysOf(int $businessKey, array $customerIds): array
    {
        return DB::table(self::CUSTOMERS_TABLE)
            ->where('business_id', $businessKey)
            ->whereIn('uuid', $customerIds)
            ->pluck('id')
            ->map(static fn (mixed $key): int => (int) $key)
            ->values()
            ->all();
    }

    private static function saleSortColumn(SaleSort $sort): string
    {
        return match ($sort) {
            SaleSort::CreatedAt => 'payments.created_at',
            SaleSort::Total => 'payments.total_cents',
        };
    }

    private static function signedAmount(): string
    {
        $branches = array_map(
            static fn (PaymentTransactionType $type): string => sprintf(
                "when '%s' then %d * payment_transactions.total_cents",
                $type->value,
                $type->sign(),
            ),
            PaymentTransactionType::cases(),
        );

        return '(case payment_transactions.type '.implode(' ', $branches).' end)';
    }

    /**
     * @template TRecord
     *
     * @param  list<stdClass>  $rows
     * @param  callable(stdClass): TRecord  $toRecord
     * @return Paginated<TRecord>
     */
    private static function pageOf(array $rows, int $total, ReportCriteria $criteria, callable $toRecord): Paginated
    {
        return Paginated::of(array_values(array_map($toRecord, $rows)), $total, $criteria->pagination);
    }

    private static function saleFrom(stdClass $row): SaleRecord
    {
        return new SaleRecord(
            id: (string) $row->id,
            createdAt: self::instantFrom((string) $row->created_at),
            customerId: (string) $row->customer_id,
            customerName: (string) $row->customer_name,
            status: PaymentStatus::forAmounts((int) $row->paid_cents, (int) $row->total_cents),
            totalCents: (int) $row->total_cents,
            currencyCode: (string) $row->currency_code,
            referenceCode: (string) $row->reference_code,
        );
    }

    private static function transactionFrom(stdClass $row): TransactionRecord
    {
        return new TransactionRecord(
            id: (string) $row->id,
            processedAt: self::instantFrom((string) $row->processed_at),
            customerId: (string) $row->customer_id,
            customerName: (string) $row->customer_name,
            type: PaymentTransactionType::from((string) $row->type),
            totalCents: (int) $row->total_cents,
            currencyCode: (string) $row->currency_code,
            paymentMethodCode: (string) $row->payment_method_code,
        );
    }

    private static function instantFrom(string $stored): DateTimeImmutable
    {
        $storageZone = new DateTimeZone(self::STORAGE_TIMEZONE);

        return (new DateTimeImmutable($stored, $storageZone))->setTimezone($storageZone);
    }

    private static function storedInstant(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE))->format(DATE_ATOM);
    }

    private static function escapeLike(string $fragment): string
    {
        return str_replace(self::LIKE_METACHARACTERS, self::ESCAPED_LIKE_METACHARACTERS, $fragment);
    }
}
