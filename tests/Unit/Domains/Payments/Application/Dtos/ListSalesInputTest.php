<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\ListSalesInput;
use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Domains\Payments\ValueObjects\SaleSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\Payments\PaymentReportFixtures;

describe('reading a query string', function () {
    it('assembles itself and its criteria from a well formed query string', function () {
        $input = ListSalesInput::fromRequest([
            'from' => '2026-03-01',
            'to' => '2026-03-31',
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID],
            'direction' => 'asc',
            'page' => 2,
            'per_page' => 10,
            'reference' => 'mz7k',
            'statuses' => ['paid', 'pending'],
            'sort' => 'total',
        ]);

        expect($input->reference)->toBe('mz7k')
            ->and($input->statuses)->toBe(['paid', 'pending'])
            ->and($input->sort)->toBe('total')
            ->and($input->criteria->from)->toBe('2026-03-01')
            ->and($input->criteria->to)->toBe('2026-03-31')
            ->and($input->criteria->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID])
            ->and($input->criteria->direction)->toBe('asc')
            ->and($input->criteria->page)->toBe(2)
            ->and($input->criteria->perPage)->toBe(10);
    });

    it('survives a query string with every key missing', function () {
        $input = ListSalesInput::fromRequest([]);

        expect($input->reference)->toBeNull()
            ->and($input->statuses)->toBe([])
            ->and($input->sort)->toBeNull()
            ->and($input->criteria->from)->toBeNull()
            ->and($input->criteria->customerIds)->toBe([]);
    });

    it('reads a wrongly typed value as none', function (array $payload, string $field) {
        expect(ListSalesInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'reference as an array' => [['reference' => ['MZ7K']], 'reference'],
        'reference as a number' => [['reference' => 2749], 'reference'],
        'sort as an array' => [['sort' => ['total']], 'sort'],
        'sort as a boolean' => [['sort' => true], 'sort'],
    ]);

    it('filters on no status when the statuses do not arrive as a list', function (mixed $statuses) {
        expect(ListSalesInput::fromRequest(['statuses' => $statuses])->statuses)->toBe([]);
    })->with(['a single string' => 'paid', 'null' => null]);
});

describe('validating', function () {
    it('accepts a query string every rule agrees with', function () {
        expect(fn () => ListSalesInput::fromRequest([
            'from' => '2026-03-01',
            'to' => '2026-03-31',
            'reference' => 'mz7k',
            'statuses' => ['pending', 'partially_paid', 'paid'],
            'sort' => 'created_at',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an empty query string', function () {
        expect(fn () => ListSalesInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a reference it can look for', function (string $reference) {
        expect(fn () => ListSalesInput::fromRequest(['reference' => $reference])->validate())->not->toThrow(Throwable::class);
    })->with([
        'as long as a reference code' => str_repeat('A', ListSalesInput::MAXIMUM_REFERENCE_LENGTH),
        'padded past that length with whitespace' => '  MZ7K2QP9  ',
        'blank' => '   ',
        'empty' => '',
    ]);

    it('accepts the same status twice', function () {
        expect(fn () => ListSalesInput::fromRequest(['statuses' => ['paid', 'paid']])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a filter or a sort it cannot serve', function (array $payload, string $reason) {
        expect(fn () => ListSalesInput::fromRequest($payload)->validate())
            ->toThrow(InvalidPaymentReportFilter::class, $reason);
    })->with([
        'a status no sale has' => [['statuses' => ['refunded']], 'The payment status [refunded]'],
        'an uppercase status' => [['statuses' => ['PAID']], 'The payment status [PAID]'],
        'an empty status' => [['statuses' => ['']], 'The payment status []'],
        'a status that is not a string' => [['statuses' => [1]], 'The payment status []'],
        'an unknown status after a good one' => [['statuses' => ['paid', 'void']], 'The payment status [void]'],
        'a reference one character longer than a code' => [
            ['reference' => str_repeat('A', ListSalesInput::MAXIMUM_REFERENCE_LENGTH + 1)],
            'may not run past 8 characters',
        ],
        'a sort by a column it does not serve' => [['sort' => 'business_id'], 'cannot be sorted by [business_id]'],
        'the sort of the transactions report' => [['sort' => 'amount'], 'cannot be sorted by [amount]'],
        'an uppercase sort' => [['sort' => 'TOTAL'], 'cannot be sorted by [TOTAL]'],
        'an empty sort' => [['sort' => ''], 'cannot be sorted by []'],
    ]);

    it('revalidates the criteria it carries', function (array $payload, string $failure) {
        expect(fn () => ListSalesInput::fromRequest($payload)->validate())->toThrow($failure);
    })->with([
        'an incomplete period' => [['from' => '2026-03-01'], InvalidPaymentReportPeriod::class],
        'a malformed customer' => [['customer_ids' => ['42']], InvalidPaymentReportFilter::class],
        'a page past the largest' => [['per_page' => 101], InvalidPaymentReportFilter::class],
    ]);

    it('judges the criteria before its own filters', function () {
        expect(fn () => ListSalesInput::fromRequest([
            'from' => '2026-03-31',
            'to' => '2026-03-01',
            'statuses' => ['refunded'],
            'sort' => 'nope',
        ])->validate())->toThrow(InvalidPaymentReportPeriod::class);
    });

    it('judges the reference before the statuses and the statuses before the sort', function () {
        expect(fn () => ListSalesInput::fromRequest([
            'reference' => str_repeat('A', 9),
            'statuses' => ['refunded'],
            'sort' => 'nope',
        ])->validate())->toThrow(InvalidPaymentReportFilter::class, 'may not run past 8 characters')
            ->and(fn () => ListSalesInput::fromRequest([
                'statuses' => ['refunded'],
                'sort' => 'nope',
            ])->validate())->toThrow(InvalidPaymentReportFilter::class, 'The payment status [refunded]');
    });
});

describe('the period it asks for', function () {
    it('asks for the period its criteria hold', function () {
        $period = ListSalesInput::fromRequest(['from' => '2026-03-08', 'to' => '2026-03-09'])->period();

        expect($period?->from->toString())->toBe('2026-03-08')
            ->and($period?->to->toString())->toBe('2026-03-09');
    });

    it('asks for no period when no date was given', function () {
        expect(ListSalesInput::fromRequest([])->period())->toBeNull();
    });
});

describe('turning itself into a query', function () {
    it('carries every filter, the sort and the criteria it was given', function () {
        $window = new ReportWindow(
            startsAt: new DateTimeImmutable('2026-03-01T05:00:00+00:00'),
            endsAt: new DateTimeImmutable('2026-04-01T04:00:00+00:00'),
        );

        $query = ListSalesInput::fromRequest([
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID],
            'direction' => 'asc',
            'page' => 2,
            'per_page' => 10,
            'reference' => '  mz7k ',
            'statuses' => ['partially_paid', 'pending'],
            'sort' => 'total',
        ])->toQuery($window);

        expect($query->reference?->value)->toBe('MZ7K')
            ->and($query->statuses)->toBe([PaymentStatus::PartiallyPaid, PaymentStatus::Pending])
            ->and($query->sort)->toBe(SaleSort::Total)
            ->and($query->criteria->window)->toBe($window)
            ->and($query->criteria->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID])
            ->and($query->criteria->direction)->toBe(SortDirection::Ascending)
            ->and($query->criteria->pagination->page)->toBe(2)
            ->and($query->criteria->pagination->perPage)->toBe(10);
    });

    it('lists every sale newest first, twenty to a page, by default', function () {
        $query = ListSalesInput::fromRequest([])->toQuery(null);

        expect($query->reference)->toBeNull()
            ->and($query->statuses)->toBe([])
            ->and($query->sort)->toBe(SaleSort::CreatedAt)
            ->and($query->criteria->window)->toBeNull()
            ->and($query->criteria->customerIds)->toBe([])
            ->and($query->criteria->direction)->toBe(SortDirection::Descending)
            ->and($query->criteria->pagination->page)->toBe(1)
            ->and($query->criteria->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('asks for each status once, in the order they first appeared', function () {
        expect(ListSalesInput::fromRequest(['statuses' => ['paid', 'pending', 'paid', 'pending']])->toQuery(null)->statuses)
            ->toBe([PaymentStatus::Paid, PaymentStatus::Pending]);
    });

    it('looks for no reference when the one it was given is blank', function (?string $reference) {
        expect(ListSalesInput::fromRequest(['reference' => $reference])->toQuery(null)->reference)->toBeNull();
    })->with(['missing' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t"]);
});
