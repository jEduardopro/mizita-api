<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\ListSalesInput;
use App\Domains\Payments\Application\Dtos\SaleData;
use App\Domains\Payments\Application\UseCases\ListSales;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\SaleSort;
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

    $this->build = fn (?FakeBusinessContext $business = null): ListSales => new ListSales(
        $this->reports,
        $business ?? new FakeBusinessContext,
        $this->timezones,
    );

    $this->list = fn (array $payload = []) => ($this->build)()->handle(ListSalesInput::fromRequest($payload));
});

describe('the page it answers with', function () {
    it('answers with every sale the report found, field by field', function () {
        $this->reports->returningSales(Paginated::of(
            [
                PaymentReportFixtures::sale(),
                PaymentReportFixtures::sale(
                    id: PaymentReportFixtures::SECOND_SALE_ID,
                    customerId: PaymentReportFixtures::SECOND_CUSTOMER_ID,
                    customerName: PaymentReportFixtures::SECOND_CUSTOMER_NAME,
                    status: PaymentStatus::Paid,
                    totalCents: 0,
                    currencyCode: PaymentFixtures::OTHER_CURRENCY,
                    referenceCode: 'QX4R8TZ1',
                    createdAt: '2026-03-09T03:59:59+00:00',
                ),
            ],
            42,
            Pagination::of(2, 10),
        ));

        $page = ($this->list)(['page' => 2, 'per_page' => 10])->value();

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(2)
            ->and($page->pagination->perPage)->toBe(10)
            ->and($page->items)->toHaveCount(2)
            ->and($page->items[0])->toBeInstanceOf(SaleData::class)
            ->and($page->items[0]->id)->toBe(PaymentReportFixtures::SALE_ID)
            ->and($page->items[0]->createdAt)->toEqual(new DateTimeImmutable(PaymentReportFixtures::CREATED_AT))
            ->and($page->items[0]->customerId)->toBe(PaymentReportFixtures::CUSTOMER_ID)
            ->and($page->items[0]->customerName)->toBe(PaymentReportFixtures::CUSTOMER_NAME)
            ->and($page->items[0]->status)->toBe(PaymentStatus::PartiallyPaid)
            ->and($page->items[0]->totalCents)->toBe(65_000)
            ->and($page->items[0]->currencyCode)->toBe(PaymentFixtures::CURRENCY)
            ->and($page->items[0]->referenceCode)->toBe(PaymentReportFixtures::REFERENCE_CODE)
            ->and($page->items[1]->id)->toBe(PaymentReportFixtures::SECOND_SALE_ID)
            ->and($page->items[1]->createdAt)->toEqual(new DateTimeImmutable('2026-03-09T03:59:59+00:00'))
            ->and($page->items[1]->customerId)->toBe(PaymentReportFixtures::SECOND_CUSTOMER_ID)
            ->and($page->items[1]->customerName)->toBe(PaymentReportFixtures::SECOND_CUSTOMER_NAME)
            ->and($page->items[1]->status)->toBe(PaymentStatus::Paid)
            ->and($page->items[1]->totalCents)->toBe(0)
            ->and($page->items[1]->currencyCode)->toBe(PaymentFixtures::OTHER_CURRENCY)
            ->and($page->items[1]->referenceCode)->toBe('QX4R8TZ1');
    });

    it('hands back each sale and its customer under the uuid the client already holds', function () {
        $this->reports->returningSales(Paginated::of([PaymentReportFixtures::sale()], 1, Pagination::of(1, 20)));

        $sale = ($this->list)()->value()->items[0];

        expect($sale->id)->toBe(PaymentReportFixtures::SALE_ID)->toMatch('/^[0-9a-f-]{36}$/')
            ->and($sale->customerId)->toBe(PaymentReportFixtures::CUSTOMER_ID)->toMatch('/^[0-9a-f-]{36}$/');
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
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID, PaymentReportFixtures::CUSTOMER_ID],
            'statuses' => ['pending', 'partially_paid', 'pending'],
            'reference' => '  mz7k ',
            'sort' => 'total',
            'direction' => 'asc',
            'page' => 3,
            'per_page' => 50,
        ]);

        $query = $this->reports->salesQueries[0];

        expect($this->reports->salesQueries)->toHaveCount(1)
            ->and($query->criteria->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID])
            ->and($query->statuses)->toBe([PaymentStatus::Pending, PaymentStatus::PartiallyPaid])
            ->and($query->reference?->value)->toBe('MZ7K')
            ->and($query->sort)->toBe(SaleSort::Total)
            ->and($query->criteria->direction)->toBe(SortDirection::Ascending)
            ->and($query->criteria->pagination->page)->toBe(3)
            ->and($query->criteria->pagination->perPage)->toBe(50);
    });

    it('asks for every sale newest first, twenty to a page, when nothing was asked for', function () {
        ($this->list)();

        $query = $this->reports->salesQueries[0];

        expect($query->criteria->window)->toBeNull()
            ->and($query->criteria->customerIds)->toBe([])
            ->and($query->statuses)->toBe([])
            ->and($query->reference)->toBeNull()
            ->and($query->sort)->toBe(SaleSort::CreatedAt)
            ->and($query->criteria->direction)->toBe(SortDirection::Descending)
            ->and($query->criteria->pagination->page)->toBe(1)
            ->and($query->criteria->pagination->perPage)->toBe(20);
    });

    it('never reads the transactions report', function () {
        ($this->list)();

        expect($this->reports->transactionsQueries)->toBe([]);
    });
});

describe('filtering by period', function () {
    it('hands the report the window the period covers in the business timezone', function () {
        ($this->list)(['from' => '2026-03-01', 'to' => '2026-03-31']);

        $window = $this->reports->salesQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-03-01T05:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-04-01T04:00:00+00:00');
    });

    it('covers the twenty-three hours of a spring forward day', function () {
        ($this->list)(['from' => '2026-03-08', 'to' => '2026-03-08']);

        $window = $this->reports->salesQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-03-08T05:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-03-09T04:00:00+00:00');
    });

    it('covers the twenty-five hours of a fall back day', function () {
        ($this->list)(['from' => '2026-11-01', 'to' => '2026-11-01']);

        $window = $this->reports->salesQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-11-01T04:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-11-02T05:00:00+00:00');
    });

    it('reads the dates in the timezone of the business in context', function () {
        ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(ListSalesInput::fromRequest(['from' => '2026-10-25', 'to' => '2026-10-25']));

        $window = $this->reports->salesQueries[0]->criteria->window;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-10-24T22:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-10-25T23:00:00+00:00');
    });

    it('asks the timezone of the business in context, once', function () {
        ($this->list)(['from' => '2026-03-01', 'to' => '2026-03-31']);

        expect($this->timezones->lookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('asks no timezone and filters on no window when no period was given', function (array $payload) {
        ($this->list)($payload);

        expect($this->timezones->lookups)->toBe([])
            ->and($this->reports->salesQueries[0]->criteria->window)->toBeNull();
    })->with([
        'no dates' => [[]],
        'blank dates' => [['from' => '', 'to' => '   ']],
        'other filters only' => [['statuses' => ['paid'], 'reference' => 'MZ7K']],
    ]);

    it('lists a business the timezone lookup does not know, as long as no period was given', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::UNKNOWN_ID))
            ->handle(ListSalesInput::fromRequest([]));

        expect($response->succeeded())->toBeTrue()
            ->and($this->reports->businessIdsSeen)->toBe([PaymentFixtures::UNKNOWN_ID]);
    });
});

describe('the business it reads', function () {
    it('reads the sales of the business in context', function () {
        ($this->list)(['from' => '2026-03-01', 'to' => '2026-03-31']);

        expect($this->reports->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('reads another business only when the context names it', function () {
        ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(ListSalesInput::fromRequest(['from' => '2026-03-01', 'to' => '2026-03-31']));

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
            ->and($this->reports->salesQueries)->toBe([])
            ->and($this->reports->businessIdsSeen)->toBe([])
            ->and($this->timezones->lookups)->toBe([]);
    })->with([
        'a malformed date' => [['from' => '2026-02-30', 'to' => '2026-03-31'], 'invalid_payment_report_period'],
        'an incomplete period' => [['from' => '2026-03-01'], 'invalid_payment_report_period'],
        'an inverted period' => [['from' => '2026-03-31', 'to' => '2026-03-01'], 'invalid_payment_report_period'],
        'a malformed customer' => [['customer_ids' => ['42']], 'invalid_payment_report_filter'],
        'too many customers' => [['customer_ids' => PaymentReportFixtures::customerIds(101)], 'invalid_payment_report_filter'],
        'an unknown status' => [['statuses' => ['refunded']], 'invalid_payment_report_filter'],
        'a reference too long' => [['reference' => 'MZ7K2QP9X'], 'invalid_payment_report_filter'],
        'an unknown sort' => [['sort' => 'amount'], 'invalid_payment_report_filter'],
        'an unknown direction' => [['direction' => 'sideways'], 'invalid_payment_report_filter'],
        'a page out of range' => [['page' => 0], 'invalid_payment_report_filter'],
        'a page size out of range' => [['per_page' => 101], 'invalid_payment_report_filter'],
    ]);

    it('answers not found when the business behind a period is gone, without reading its sales', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::UNKNOWN_ID))
            ->handle(ListSalesInput::fromRequest(['from' => '2026-03-01', 'to' => '2026-03-31']));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->timezones->lookups)->toBe([PaymentFixtures::UNKNOWN_ID])
            ->and($this->reports->salesQueries)->toBe([]);
    });

    it('lets a failure that is no refusal escape to the caller', function () {
        $this->reports->failingWith(new RuntimeException('connection lost'));

        expect(fn () => ($this->list)())->toThrow(RuntimeException::class, 'connection lost');
    });
});
