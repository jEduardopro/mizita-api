<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentReportCriteriaInput;
use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\ReportPeriod;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\Payments\PaymentReportFixtures;

describe('reading a query string', function () {
    it('assembles itself from a well formed query string', function () {
        $input = PaymentReportCriteriaInput::fromRequest([
            'from' => '2026-03-01',
            'to' => '2026-03-31',
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID],
            'direction' => 'asc',
            'page' => 3,
            'per_page' => 50,
        ]);

        expect($input->from)->toBe('2026-03-01')
            ->and($input->to)->toBe('2026-03-31')
            ->and($input->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID])
            ->and($input->direction)->toBe('asc')
            ->and($input->page)->toBe(3)
            ->and($input->perPage)->toBe(50);
    });

    it('survives a query string with every key missing', function () {
        $input = PaymentReportCriteriaInput::fromRequest([]);

        expect($input->from)->toBeNull()
            ->and($input->to)->toBeNull()
            ->and($input->customerIds)->toBe([])
            ->and($input->direction)->toBeNull()
            ->and($input->page)->toBeNull()
            ->and($input->perPage)->toBeNull();
    });

    it('reads a blank date as no date at all', function (string $blank) {
        $input = PaymentReportCriteriaInput::fromRequest(['from' => $blank, 'to' => $blank]);

        expect($input->from)->toBeNull()
            ->and($input->to)->toBeNull();
    })->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

    it('holds a date as it arrived, leaving the date to read it', function () {
        expect(PaymentReportCriteriaInput::fromRequest(['from' => ' 2026-3-1 '])->from)->toBe(' 2026-3-1 ');
    });

    it('reads the numbers a query string carries as strings', function () {
        $input = PaymentReportCriteriaInput::fromRequest(['page' => '2', 'per_page' => '50']);

        expect($input->page)->toBe(2)
            ->and($input->perPage)->toBe(50);
    });

    it('reads a wrongly typed value as none', function (array $payload, string $field) {
        expect(PaymentReportCriteriaInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'from as an array' => [['from' => ['2026-03-01']], 'from'],
        'from as a number' => [['from' => 20260301], 'from'],
        'to as a boolean' => [['to' => true], 'to'],
        'to as null' => [['to' => null], 'to'],
        'direction as an array' => [['direction' => ['asc']], 'direction'],
        'direction as a boolean' => [['direction' => true], 'direction'],
        'page as a word' => [['page' => 'first'], 'page'],
        'page as an array' => [['page' => [2]], 'page'],
        'per page as a word' => [['per_page' => 'all'], 'perPage'],
    ]);

    it('filters on no customer when the customers do not arrive as a list', function (mixed $customerIds) {
        expect(PaymentReportCriteriaInput::fromRequest(['customer_ids' => $customerIds])->customerIds)->toBe([]);
    })->with([
        'a single string' => PaymentReportFixtures::CUSTOMER_ID,
        'null' => null,
        'a number' => 42,
    ]);

    it('reindexes a keyed list of customers', function () {
        expect(PaymentReportCriteriaInput::fromRequest([
            'customer_ids' => ['first' => PaymentReportFixtures::CUSTOMER_ID, 7 => PaymentReportFixtures::SECOND_CUSTOMER_ID],
        ])->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID]);
    });

    it('keeps a customer that is not a string, so validation can refuse it instead of a type error', function () {
        $input = PaymentReportCriteriaInput::fromRequest(['customer_ids' => [42]]);

        expect(fn () => $input->validate())
            ->toThrow(InvalidPaymentReportFilter::class, 'The customer filter [] is not a well-formed identifier.');
    });
});

describe('validating', function () {
    it('accepts a query string every rule agrees with', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest([
            'from' => '2026-03-01',
            'to' => '2026-03-31',
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID],
            'direction' => 'desc',
            'page' => 2,
            'per_page' => 25,
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an empty query string, because a report needs no arguments', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts the boundaries it serves', function (array $payload) {
        expect(fn () => PaymentReportCriteriaInput::fromRequest($payload)->validate())->not->toThrow(Throwable::class);
    })->with([
        'the first page' => [['page' => PaymentReportCriteriaInput::FIRST_PAGE]],
        'the deepest page' => [['page' => Pagination::MAXIMUM_PAGE]],
        'a page of one row' => [['per_page' => PaymentReportCriteriaInput::MINIMUM_PER_PAGE]],
        'the largest page' => [['per_page' => Pagination::MAXIMUM_PER_PAGE]],
        'as many customers as it filters by' => [['customer_ids' => PaymentReportFixtures::customerIds(PaymentReportCriteriaInput::MAXIMUM_CUSTOMER_FILTER_SIZE)]],
        'an uppercase customer uuid' => [['customer_ids' => [strtoupper(PaymentReportFixtures::CUSTOMER_ID)]]],
        'the same customer twice' => [['customer_ids' => [PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::CUSTOMER_ID]]],
        'a single day period' => [['from' => '2026-03-08', 'to' => '2026-03-08']],
        'dates padded with whitespace' => [['from' => ' 2026-03-01 ', 'to' => '2026-03-31  ']],
        'ascending' => [['direction' => 'asc']],
        'descending' => [['direction' => 'desc']],
    ]);

    it('refuses a filter it cannot serve', function (array $payload, string $reason) {
        expect(fn () => PaymentReportCriteriaInput::fromRequest($payload)->validate())
            ->toThrow(InvalidPaymentReportFilter::class, $reason);
    })->with([
        'one customer more than it filters by' => [
            ['customer_ids' => PaymentReportFixtures::customerIds(PaymentReportCriteriaInput::MAXIMUM_CUSTOMER_FILTER_SIZE + 1)],
            'at most 100 customers',
        ],
        'a customer that is a sequential int' => [['customer_ids' => ['42']], 'The customer filter [42]'],
        'a customer that is a word' => [['customer_ids' => ['ada']], 'The customer filter [ada]'],
        'an empty customer' => [['customer_ids' => ['']], 'The customer filter []'],
        'a customer uuid with trailing whitespace' => [
            ['customer_ids' => [PaymentReportFixtures::CUSTOMER_ID.' ']],
            'is not a well-formed identifier',
        ],
        'a malformed customer after a good one' => [
            ['customer_ids' => [PaymentReportFixtures::CUSTOMER_ID, 'nope']],
            'The customer filter [nope]',
        ],
        'a direction that does not exist' => [['direction' => 'sideways'], 'The sort direction [sideways]'],
        'an uppercase direction' => [['direction' => 'DESC'], 'The sort direction [DESC]'],
        'an empty direction' => [['direction' => ''], 'The sort direction []'],
        'the zeroth page' => [['page' => 0], 'The page [0]'],
        'a negative page' => [['page' => -1], 'The page [-1]'],
        'a page of no rows' => [['per_page' => 0], 'got [0]'],
        'a negative page size' => [['per_page' => -5], 'got [-5]'],
        'one row more than a page holds' => [['per_page' => Pagination::MAXIMUM_PER_PAGE + 1], 'between 1 and 100 rows, got [101]'],
    ]);

    it('refuses a period it cannot read', function (array $payload, string $reason) {
        expect(fn () => PaymentReportCriteriaInput::fromRequest($payload)->validate())
            ->toThrow(InvalidPaymentReportPeriod::class, $reason);
    })->with([
        'a from date that is not a calendar day' => [
            ['from' => '2026-02-30', 'to' => '2026-03-31'],
            'The report date [2026-02-30] is not a calendar date',
        ],
        'a to date that is not a calendar day' => [
            ['from' => '2026-02-01', 'to' => '2026-02-30'],
            'The report date [2026-02-30] is not a calendar date',
        ],
        'a from date without padding' => [
            ['from' => '2026-3-1', 'to' => '2026-03-31'],
            'The report date [2026-3-1] is not a calendar date',
        ],
        'a to date written day first' => [
            ['from' => '2026-03-01', 'to' => '31/03/2026'],
            'The report date [31/03/2026] is not a calendar date',
        ],
        'only a from date' => [['from' => '2026-03-01'], 'needs both a from and a to date'],
        'only a to date' => [['to' => '2026-03-31'], 'needs both a from and a to date'],
        'a from date and a blank to date' => [['from' => '2026-03-01', 'to' => '   '], 'needs both a from and a to date'],
        'a reversed period' => [['from' => '2026-03-31', 'to' => '2026-03-01'], 'got [2026-03-31] to [2026-03-01]'],
        'a period wider than five years' => [['from' => '2020-01-01', 'to' => '2026-03-31'], 'may span at most [5] years'],
    ]);

    it('accepts a period of exactly five years', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest(['from' => '2021-03-31', 'to' => '2026-03-31'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a page past the deepest one the form request lets through', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest(['page' => Pagination::MAXIMUM_PAGE + 1])->validate())
            ->toThrow(InvalidPaymentReportFilter::class, 'The page [10001]');
    });

    it('refuses a single malformed bound as incomplete before reading it', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest(['from' => 'yesterday'])->validate())
            ->toThrow(InvalidPaymentReportPeriod::class, 'needs both a from and a to date');
    });

    it('judges the period before any filter', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest([
            'to' => '2026-03-31',
            'customer_ids' => ['nope'],
            'page' => 0,
        ])->validate())->toThrow(InvalidPaymentReportPeriod::class);
    });

    it('refuses a filter with a failure the responder can classify', function () {
        $refusal = null;

        try {
            PaymentReportCriteriaInput::fromRequest(['page' => 0])->validate();
        } catch (InvalidPaymentReportFilter $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_payment_report_filter')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a period with a failure the responder can classify', function () {
        $refusal = null;

        try {
            PaymentReportCriteriaInput::fromRequest(['from' => '2026-03-01'])->validate();
        } catch (InvalidPaymentReportPeriod $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_payment_report_period')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('the period it asks for', function () {
    it('asks for no period when neither date was given', function () {
        expect(PaymentReportCriteriaInput::fromRequest([])->period())->toBeNull();
    });

    it('asks for the period between the two dates it was given', function () {
        $period = PaymentReportCriteriaInput::fromRequest(['from' => '2026-03-01', 'to' => '2026-03-31'])->period();

        expect($period)->toBeInstanceOf(ReportPeriod::class)
            ->and($period?->from->toString())->toBe('2026-03-01')
            ->and($period?->to->toString())->toBe('2026-03-31');
    });

    it('reads the dates after trimming them', function () {
        $period = PaymentReportCriteriaInput::fromRequest(['from' => ' 2026-03-01 ', 'to' => "2026-03-31\n"])->period();

        expect($period?->from->toString())->toBe('2026-03-01')
            ->and($period?->to->toString())->toBe('2026-03-31');
    });

    it('refuses to answer for a period missing one of its dates', function () {
        expect(fn () => PaymentReportCriteriaInput::fromRequest(['to' => '2026-03-31'])->period())
            ->toThrow(InvalidPaymentReportPeriod::class);
    });
});

describe('turning itself into criteria', function () {
    beforeEach(function () {
        $this->window = new ReportWindow(
            startsAt: new DateTimeImmutable('2026-03-08T05:00:00+00:00'),
            endsAt: new DateTimeImmutable('2026-03-09T04:00:00+00:00'),
        );
    });

    it('carries the window it is handed', function () {
        expect(PaymentReportCriteriaInput::fromRequest([])->toCriteria($this->window)->window)->toBe($this->window);
    });

    it('carries no window unless it is handed one, whatever dates it holds', function () {
        expect(PaymentReportCriteriaInput::fromRequest(['from' => '2026-03-01', 'to' => '2026-03-31'])->toCriteria(null)->window)
            ->toBeNull();
    });

    it('carries the customers, the direction and the pagination it was given', function () {
        $criteria = PaymentReportCriteriaInput::fromRequest([
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID],
            'direction' => 'asc',
            'page' => 3,
            'per_page' => 50,
        ])->toCriteria($this->window);

        expect($criteria->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID, PaymentReportFixtures::SECOND_CUSTOMER_ID])
            ->and($criteria->direction)->toBe(SortDirection::Ascending)
            ->and($criteria->pagination->page)->toBe(3)
            ->and($criteria->pagination->perPage)->toBe(50);
    });

    it('asks for each customer once, in the order they first appeared', function () {
        $criteria = PaymentReportCriteriaInput::fromRequest([
            'customer_ids' => [
                PaymentReportFixtures::SECOND_CUSTOMER_ID,
                PaymentReportFixtures::CUSTOMER_ID,
                PaymentReportFixtures::SECOND_CUSTOMER_ID,
                PaymentReportFixtures::CUSTOMER_ID,
            ],
        ])->toCriteria(null);

        expect($criteria->customerIds)->toBe([PaymentReportFixtures::SECOND_CUSTOMER_ID, PaymentReportFixtures::CUSTOMER_ID]);
    });

    it('lists the newest first on the first page of twenty rows by default', function () {
        $criteria = PaymentReportCriteriaInput::fromRequest([])->toCriteria(null);

        expect($criteria->customerIds)->toBe([])
            ->and($criteria->direction)->toBe(SortDirection::Descending)
            ->and($criteria->pagination->page)->toBe(1)
            ->and($criteria->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE)
            ->and(Pagination::DEFAULT_PER_PAGE)->toBe(20);
    });
});
