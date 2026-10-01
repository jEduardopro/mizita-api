<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\ListTransactionsInput;
use App\Domains\Payments\Application\Dtos\PaymentTransactionReportData;
use App\Domains\Payments\Application\UseCases\ListTransactions;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\TransactionSort;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Payments\FakeBusinessTimezone;
use Tests\Support\Payments\FakePaymentReports;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentReportFixtures;

beforeEach(function () {
    $this->reports = new FakePaymentReports;
    $this->timezones = (new FakeBusinessTimezone)
        ->add(FakeBusinessContext::BUSINESS_ID, PaymentReportFixtures::NEW_YORK)
        ->add(PaymentFixtures::OTHER_BUSINESS_ID, PaymentReportFixtures::MADRID);

    $this->build = fn (?FakeBusinessContext $business = null): ListTransactions => new ListTransactions(
        $this->reports,
        $business ?? new FakeBusinessContext,
        $this->timezones,
    );

    $this->list = fn (array $payload = []) => ($this->build)()->handle(ListTransactionsInput::fromRequest($payload));
});

describe('the page it answers with', function () {
    it('answers with every transaction the report found, field by field', function () {
        $this->reports->returningTransactions(Paginated::of(
            [
                PaymentReportFixtures::transaction(),
                PaymentReportFixtures::transaction(
                    id: PaymentFixtures::SECOND_TRANSACTION_ID,
                    type: PaymentTransactionType::Void,
                    totalCents: 15_000,
                    customerId: PaymentReportFixtures::SECOND_CUSTOMER_ID,
                    customerName: PaymentReportFixtures::SECOND_CUSTOMER_NAME,
                    currencyCode: PaymentFixtures::OTHER_CURRENCY,
                    paymentMethodCode: 'bank_transfer',
                    processedAt: '2026-11-01T05:30:00+00:00',
                ),
            ],
            37,
            Pagination::of(3, 15),
        ));

        $page = ($this->list)(['page' => 3, 'per_page' => 15])->value();

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($page->total)->toBe(37)
            ->and($page->pagination->page)->toBe(3)
            ->and($page->pagination->perPage)->toBe(15)
            ->and($page->items)->toHaveCount(2)
            ->and($page->items[0])->toBeInstanceOf(PaymentTransactionReportData::class)
            ->and($page->items[0]->id)->toBe(PaymentFixtures::TRANSACTION_ID)
            ->and($page->items[0]->processedAt)->toEqual(new DateTimeImmutable(PaymentReportFixtures::PROCESSED_AT))
            ->and($page->items[0]->customerId)->toBe(PaymentReportFixtures::CUSTOMER_ID)
            ->and($page->items[0]->customerName)->toBe(PaymentReportFixtures::CUSTOMER_NAME)
            ->and($page->items[0]->amountCents)->toBe(40_000)
            ->and($page->items[0]->currencyCode)->toBe(PaymentFixtures::CURRENCY)
            ->and($page->items[0]->type)->toBe(PaymentTransactionType::Approved)
            ->and($page->items[0]->paymentMethodCode)->toBe('cash')
            ->and($page->items[1]->id)->toBe(PaymentFixtures::SECOND_TRANSACTION_ID)
            ->and($page->items[1]->processedAt)->toEqual(new DateTimeImmutable('2026-11-01T05:30:00+00:00'))
            ->and($page->items[1]->customerId)->toBe(PaymentReportFixtures::SECOND_CUSTOMER_ID)
            ->and($page->items[1]->customerName)->toBe(PaymentReportFixtures::SECOND_CUSTOMER_NAME)
            ->and($page->items[1]->amountCents)->toBe(-15_000)
            ->and($page->items[1]->currencyCode)->toBe(PaymentFixtures::OTHER_CURRENCY)
            ->and($page->items[1]->type)->toBe(PaymentTransactionType::Void)
            ->and($page->items[1]->paymentMethodCode)->toBe('bank_transfer');
    });

    it('reports each amount with the sign of its type', function (PaymentTransactionType $type, int $amount) {
        $this->reports->returningTransactions(Paginated::of(
            [PaymentReportFixtures::transaction(type: $type, totalCents: 2_500)],
            1,
            Pagination::of(1, 20),
        ));

        expect(($this->list)()->value()->items[0]->amountCents)->toBe($amount);
    })->with([
        'approved' => [PaymentTransactionType::Approved, 2_500],
        'void' => [PaymentTransactionType::Void, -2_500],
        'refund' => [PaymentTransactionType::Refund, 2_500],
        'failed' => [PaymentTransactionType::Failed, 2_500],
    ]);

    it('hands back each transaction and its customer under the uuid the client already holds', function () {
        $this->reports->returningTransactions(Paginated::of([PaymentReportFixtures::transaction()], 1, Pagination::of(1, 20)));

        $transaction = ($this->list)()->value()->items[0];

        expect($transaction->id)->toBe(PaymentFixtures::TRANSACTION_ID)->toMatch('/^[0-9a-f-]{36}$/')
            ->and($transaction->customerId)->toBe(PaymentReportFixtures::CUSTOMER_ID)->toMatch('/^[0-9a-f-]{36}$/');
    });

    it('answers an empty page with a success, not a refusal', function () {
        $response = ($this->list)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->items)->toBe([])
            ->and($response->value()->total)->toBe(0)
            ->and($response->value()->lastPage())->toBe(1);
    });
});

describe('the query it hands the report', function () {
    it('carries every filter, the sort and the pagination the input asked for', function () {
        ($this->list)([
            'customer_ids' => [PaymentReportFixtures::SECOND_CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID],
            'types' => ['void', 'refund', 'void'],
            'methods' => ['bank_transfer', 'cash', 'bank_transfer'],
            'sort' => 'amount',
            'direction' => 'asc',
            'page' => 4,
            'per_page' => 15,
        ]);

        $query = $this->reports->transactionsQueries[0];

        expect($this->reports->transactionsQueries)->toHaveCount(1)
            ->and($query->criteria->customerIds)->toBe([PaymentReportFixtures::SECOND_CUSTOMER_ID])
            ->and($query->types)->toBe([PaymentTransactionType::Void, PaymentTransactionType::Refund])
            ->and($query->methods)->toBe([PaymentMethodCode::BankTransfer, PaymentMethodCode::Cash])
            ->and($query->sort)->toBe(TransactionSort::Amount)
            ->and($query->criteria->direction)->toBe(SortDirection::Ascending)
            ->and($query->criteria->pagination->page)->toBe(4)
            ->and($query->criteria->pagination->perPage)->toBe(15);
    });

    it('asks for every transaction newest first, twenty to a page, when nothing was asked for', function () {
        ($this->list)();

        $query = $this->reports->transactionsQueries[0];

        expect($query->criteria->window)->toBeNull()
            ->and($query->criteria->customerIds)->toBe([])
            ->and($query->types)->toBe([])
            ->and($query->methods)->toBe([])
            ->and($query->sort)->toBe(TransactionSort::ProcessedAt)
            ->and($query->criteria->direction)->toBe(SortDirection::Descending)
            ->and($query->criteria->pagination->page)->toBe(1)
            ->and($query->criteria->pagination->perPage)->toBe(20);
    });

    it('never reads the sales report', function () {
        ($this->list)();

        expect($this->reports->salesQueries)->toBe([]);
    });
});

describe('filtering by period', function () {
    it('covers the twenty-three hours of a spring forward day', function () {
        ($this->list)(['from' => '2026-03-08', 'to' => '2026-03-08']);

        $window = $this->reports->transactionsQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-03-08T05:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-03-09T04:00:00+00:00');
    });

    it('covers the twenty-five hours of a fall back day', function () {
        ($this->list)(['from' => '2026-11-01', 'to' => '2026-11-01']);

        $window = $this->reports->transactionsQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-11-01T04:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-11-02T05:00:00+00:00');
    });

    it('reads the dates in the timezone of the business in context', function () {
        ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(ListTransactionsInput::fromRequest(['from' => '2026-03-29', 'to' => '2026-03-29']));

        $window = $this->reports->transactionsQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-03-28T23:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-03-29T22:00:00+00:00');
    });

    it('asks the timezone of the business in context, once', function () {
        ($this->list)(['from' => '2026-11-01', 'to' => '2026-11-30']);

        expect($this->timezones->lookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('asks no timezone and filters on no window when no period was given', function (array $payload) {
        ($this->list)($payload);

        expect($this->timezones->lookups)->toBe([])
            ->and($this->reports->transactionsQueries[0]->criteria->window)->toBeNull();
    })->with([
        'no dates' => [[]],
        'blank dates' => [['from' => "\t", 'to' => '']],
        'other filters only' => [['types' => ['void'], 'methods' => ['cash']]],
    ]);

    it('lists a business the timezone lookup does not know, as long as no period was given', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::UNKNOWN_ID))
            ->handle(ListTransactionsInput::fromRequest([]));

        expect($response->succeeded())->toBeTrue()
            ->and($this->reports->businessIdsSeen)->toBe([PaymentFixtures::UNKNOWN_ID]);
    });
});

describe('the business it reads', function () {
    it('reads the transactions of the business in context', function () {
        ($this->list)(['from' => '2026-11-01', 'to' => '2026-11-30']);

        expect($this->reports->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('reads another business only when the context names it', function () {
        ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(ListTransactionsInput::fromRequest(['from' => '2026-11-01', 'to' => '2026-11-30']));

        expect($this->reports->businessIdsSeen)->toBe([PaymentFixtures::OTHER_BUSINESS_ID])
            ->and($this->timezones->lookups)->toBe([PaymentFixtures::OTHER_BUSINESS_ID]);
    });
});

describe('refusing', function () {
    it('refuses a query it cannot serve, without reading anything', function (array $payload, string $code) {
        $response = ($this->list)($payload);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->reports->transactionsQueries)->toBe([])
            ->and($this->reports->businessIdsSeen)->toBe([])
            ->and($this->timezones->lookups)->toBe([]);
    })->with([
        'a malformed date' => [['from' => '2026-11-01', 'to' => '2026-11-31'], 'invalid_payment_report_period'],
        'an incomplete period' => [['to' => '2026-11-30'], 'invalid_payment_report_period'],
        'an inverted period' => [['from' => '2026-11-02', 'to' => '2026-11-01'], 'invalid_payment_report_period'],
        'a malformed customer' => [['customer_ids' => ['ada']], 'invalid_payment_report_filter'],
        'too many customers' => [['customer_ids' => PaymentReportFixtures::customerIds(101)], 'invalid_payment_report_filter'],
        'an unknown transaction type' => [['types' => ['chargeback']], 'invalid_payment_report_filter'],
        'an unknown payment method' => [['methods' => ['bitcoin']], 'invalid_payment_report_filter'],
        'an unknown sort' => [['sort' => 'total'], 'invalid_payment_report_filter'],
        'an unknown direction' => [['direction' => 'DESC'], 'invalid_payment_report_filter'],
        'a page out of range' => [['page' => -1], 'invalid_payment_report_filter'],
        'a page size out of range' => [['per_page' => 0], 'invalid_payment_report_filter'],
    ]);

    it('answers not found when the business behind a period is gone, without reading its transactions', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::UNKNOWN_ID))
            ->handle(ListTransactionsInput::fromRequest(['from' => '2026-11-01', 'to' => '2026-11-30']));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->timezones->lookups)->toBe([PaymentFixtures::UNKNOWN_ID])
            ->and($this->reports->transactionsQueries)->toBe([]);
    });

    it('lets a failure that is no refusal escape to the caller', function () {
        $this->reports->failingWith(new RuntimeException('connection lost'));

        expect(fn () => ($this->list)())->toThrow(RuntimeException::class, 'connection lost');
    });
});
