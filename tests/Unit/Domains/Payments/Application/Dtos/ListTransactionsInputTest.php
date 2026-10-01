<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\ListTransactionsInput;
use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\ReportWindow;
use App\Domains\Payments\ValueObjects\TransactionSort;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\Payments\PaymentReportFixtures;

describe('reading a query string', function () {
    it('assembles itself and its criteria from a well formed query string', function () {
        $input = ListTransactionsInput::fromRequest([
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'customer_ids' => [PaymentReportFixtures::CUSTOMER_ID],
            'direction' => 'asc',
            'page' => 4,
            'per_page' => 15,
            'types' => ['approved', 'void'],
            'methods' => ['cash'],
            'sort' => 'amount',
        ]);

        expect($input->types)->toBe(['approved', 'void'])
            ->and($input->methods)->toBe(['cash'])
            ->and($input->sort)->toBe('amount')
            ->and($input->criteria->from)->toBe('2026-11-01')
            ->and($input->criteria->to)->toBe('2026-11-30')
            ->and($input->criteria->customerIds)->toBe([PaymentReportFixtures::CUSTOMER_ID])
            ->and($input->criteria->direction)->toBe('asc')
            ->and($input->criteria->page)->toBe(4)
            ->and($input->criteria->perPage)->toBe(15);
    });

    it('survives a query string with every key missing', function () {
        $input = ListTransactionsInput::fromRequest([]);

        expect($input->types)->toBe([])
            ->and($input->methods)->toBe([])
            ->and($input->sort)->toBeNull()
            ->and($input->criteria->to)->toBeNull()
            ->and($input->criteria->customerIds)->toBe([]);
    });

    it('filters on nothing when a filter does not arrive as a list', function (string $field, mixed $value) {
        expect(ListTransactionsInput::fromRequest([$field => $value])->{$field})->toBe([]);
    })->with([
        'types as a single string' => ['types', 'void'],
        'types as null' => ['types', null],
        'methods as a single string' => ['methods', 'cash'],
        'methods as a number' => ['methods', 1],
    ]);

    it('reads a sort that is not a string as none', function (mixed $sort) {
        expect(ListTransactionsInput::fromRequest(['sort' => $sort])->sort)->toBeNull();
    })->with(['an array' => [['amount']], 'a boolean' => true, 'a number' => 1]);
});

describe('validating', function () {
    it('accepts a query string every rule agrees with', function () {
        expect(fn () => ListTransactionsInput::fromRequest([
            'from' => '2026-11-01',
            'to' => '2026-11-01',
            'types' => ['approved', 'void', 'refund', 'failed'],
            'methods' => ['cash', 'bank_transfer'],
            'sort' => 'processed_at',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an empty query string', function () {
        expect(fn () => ListTransactionsInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts the same type and the same method twice', function () {
        expect(fn () => ListTransactionsInput::fromRequest([
            'types' => ['void', 'void'],
            'methods' => ['cash', 'cash'],
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a filter or a sort it cannot serve', function (array $payload, string $reason) {
        expect(fn () => ListTransactionsInput::fromRequest($payload)->validate())
            ->toThrow(InvalidPaymentReportFilter::class, $reason);
    })->with([
        'a type no transaction has' => [['types' => ['chargeback']], 'The transaction type [chargeback]'],
        'a sale status as a type' => [['types' => ['paid']], 'The transaction type [paid]'],
        'an uppercase type' => [['types' => ['VOID']], 'The transaction type [VOID]'],
        'an empty type' => [['types' => ['']], 'The transaction type []'],
        'a type that is not a string' => [['types' => [false]], 'The transaction type []'],
        'a method outside the catalog' => [['methods' => ['bitcoin']], 'The payment method [bitcoin]'],
        'a method written the way people say it' => [['methods' => ['transfer']], 'The payment method [transfer]'],
        'an uppercase method' => [['methods' => ['CASH']], 'The payment method [CASH]'],
        'an unknown method after a good one' => [['methods' => ['cash', 'card']], 'The payment method [card]'],
        'a sort by a column it does not serve' => [['sort' => 'business_id'], 'cannot be sorted by [business_id]'],
        'the sort of the sales report' => [['sort' => 'total'], 'cannot be sorted by [total]'],
        'the date sort of the sales report' => [['sort' => 'created_at'], 'cannot be sorted by [created_at]'],
        'an empty sort' => [['sort' => ''], 'cannot be sorted by []'],
    ]);

    it('revalidates the criteria it carries', function (array $payload, string $failure) {
        expect(fn () => ListTransactionsInput::fromRequest($payload)->validate())->toThrow($failure);
    })->with([
        'a reversed period' => [['from' => '2026-11-02', 'to' => '2026-11-01'], InvalidPaymentReportPeriod::class],
        'a malformed date' => [['from' => '2026-11-31', 'to' => '2026-12-01'], InvalidPaymentReportPeriod::class],
        'an unknown direction' => [['direction' => 'up'], InvalidPaymentReportFilter::class],
        'the zeroth page' => [['page' => 0], InvalidPaymentReportFilter::class],
    ]);

    it('judges the criteria before its own filters', function () {
        expect(fn () => ListTransactionsInput::fromRequest([
            'to' => '2026-11-01',
            'types' => ['chargeback'],
        ])->validate())->toThrow(InvalidPaymentReportPeriod::class);
    });

    it('judges the types before the methods and the methods before the sort', function () {
        expect(fn () => ListTransactionsInput::fromRequest([
            'types' => ['chargeback'],
            'methods' => ['bitcoin'],
            'sort' => 'nope',
        ])->validate())->toThrow(InvalidPaymentReportFilter::class, 'The transaction type [chargeback]')
            ->and(fn () => ListTransactionsInput::fromRequest([
                'methods' => ['bitcoin'],
                'sort' => 'nope',
            ])->validate())->toThrow(InvalidPaymentReportFilter::class, 'The payment method [bitcoin]');
    });
});

describe('the period it asks for', function () {
    it('asks for the period its criteria hold', function () {
        $period = ListTransactionsInput::fromRequest(['from' => '2026-11-01', 'to' => '2026-11-01'])->period();

        expect($period?->from->toString())->toBe('2026-11-01')
            ->and($period?->to->toString())->toBe('2026-11-01');
    });

    it('asks for no period when no date was given', function () {
        expect(ListTransactionsInput::fromRequest([])->period())->toBeNull();
    });
});

describe('turning itself into a query', function () {
    it('carries every filter, the sort and the criteria it was given', function () {
        $window = new ReportWindow(
            startsAt: new DateTimeImmutable('2026-11-01T04:00:00+00:00'),
            endsAt: new DateTimeImmutable('2026-11-02T05:00:00+00:00'),
        );

        $query = ListTransactionsInput::fromRequest([
            'customer_ids' => [PaymentReportFixtures::SECOND_CUSTOMER_ID],
            'direction' => 'asc',
            'page' => 4,
            'per_page' => 15,
            'types' => ['void', 'approved'],
            'methods' => ['bank_transfer'],
            'sort' => 'amount',
        ])->toQuery($window);

        expect($query->types)->toBe([PaymentTransactionType::Void, PaymentTransactionType::Approved])
            ->and($query->methods)->toBe([PaymentMethodCode::BankTransfer])
            ->and($query->sort)->toBe(TransactionSort::Amount)
            ->and($query->criteria->window)->toBe($window)
            ->and($query->criteria->customerIds)->toBe([PaymentReportFixtures::SECOND_CUSTOMER_ID])
            ->and($query->criteria->direction)->toBe(SortDirection::Ascending)
            ->and($query->criteria->pagination->page)->toBe(4)
            ->and($query->criteria->pagination->perPage)->toBe(15);
    });

    it('lists every transaction newest first, twenty to a page, by default', function () {
        $query = ListTransactionsInput::fromRequest([])->toQuery(null);

        expect($query->types)->toBe([])
            ->and($query->methods)->toBe([])
            ->and($query->sort)->toBe(TransactionSort::ProcessedAt)
            ->and($query->criteria->window)->toBeNull()
            ->and($query->criteria->customerIds)->toBe([])
            ->and($query->criteria->direction)->toBe(SortDirection::Descending)
            ->and($query->criteria->pagination->page)->toBe(1)
            ->and($query->criteria->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });

    it('asks for each type and each method once, in the order they first appeared', function () {
        $query = ListTransactionsInput::fromRequest([
            'types' => ['refund', 'void', 'refund'],
            'methods' => ['cash', 'bank_transfer', 'cash', 'bank_transfer'],
        ])->toQuery(null);

        expect($query->types)->toBe([PaymentTransactionType::Refund, PaymentTransactionType::Void])
            ->and($query->methods)->toBe([PaymentMethodCode::Cash, PaymentMethodCode::BankTransfer]);
    });
});
