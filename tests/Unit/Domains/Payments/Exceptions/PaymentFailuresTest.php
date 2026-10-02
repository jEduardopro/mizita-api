<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\AppointmentAlreadyHasPayment;
use App\Domains\Payments\Exceptions\CurrencyMismatch;
use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Domains\Payments\Exceptions\InvalidMoneyAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentActor;
use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;
use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\Exceptions\PaymentAlreadySettled;
use App\Domains\Payments\Exceptions\PaymentAlreadyStarted;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use App\Domains\Payments\Exceptions\PaymentMethodNotEnabled;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\Exceptions\PaymentOverpaid;
use App\Domains\Payments\Exceptions\PaymentServiceNotFound;
use App\Domains\Payments\Exceptions\PaymentTransactionNotFound;
use App\Domains\Payments\Exceptions\PaymentTransactionNotVoidable;
use App\Domains\Payments\Exceptions\TooManyPaymentItems;
use App\Domains\Payments\Exceptions\VoidExceedsPaidAmount;
use App\Domains\Payments\ValueObjects\Money;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function paymentFailures(): array
{
    $id = '01930000-0000-7000-8000-000000000001';

    return [
        'an appointment already charged for' => [
            AppointmentAlreadyHasPayment::forAppointment($id),
            'appointment_already_has_payment',
            DomainFailureKind::Conflict,
        ],
        'two amounts in different currencies' => [
            CurrencyMismatch::between('MXN', 'USD'),
            'currency_mismatch',
            DomainFailureKind::Invalid,
        ],
        'a discount bigger than what is owed' => [
            DiscountExceedsSubtotal::of(11001, 11000),
            'discount_exceeds_subtotal',
            DomainFailureKind::Invalid,
        ],
        'a negative amount' => [
            InvalidMoneyAmount::negative(-1),
            'invalid_money_amount',
            DomainFailureKind::Invalid,
        ],
        'an amount past the largest one served' => [
            InvalidMoneyAmount::tooLarge(Money::MAXIMUM_CENTS + 1, Money::MAXIMUM_CENTS),
            'invalid_money_amount',
            DomainFailureKind::Invalid,
        ],
        'an amount that is not a decimal' => [
            InvalidMoneyAmount::malformed('1.005'),
            'invalid_money_amount',
            DomainFailureKind::Invalid,
        ],
        'a discount type nothing supports' => [
            InvalidPaymentDiscount::unknownType('coupon'),
            'invalid_payment_discount',
            DomainFailureKind::Invalid,
        ],
        'a percentage out of range' => [
            InvalidPaymentDiscount::percentageOutOfRange(10_001),
            'invalid_payment_discount',
            DomainFailureKind::Invalid,
        ],
        'a negative discount' => [
            InvalidPaymentDiscount::negativeAmount(-1),
            'invalid_payment_discount',
            DomainFailureKind::Invalid,
        ],
        'a payment recorded by a malformed account' => [
            InvalidPaymentActor::malformed('42'),
            'invalid_payment_actor',
            DomainFailureKind::Invalid,
        ],
        'a negative item amount' => [
            InvalidPaymentItemAmount::negative(-1),
            'invalid_payment_item_amount',
            DomainFailureKind::Invalid,
        ],
        'an item amount past the largest one served' => [
            InvalidPaymentItemAmount::tooLarge(Money::MAXIMUM_CENTS + 1, Money::MAXIMUM_CENTS),
            'invalid_payment_item_amount',
            DomainFailureKind::Invalid,
        ],
        'an item with no name' => [
            InvalidPaymentItemName::empty(),
            'invalid_payment_item_name',
            DomainFailureKind::Invalid,
        ],
        'an item name too long' => [
            InvalidPaymentItemName::tooLong(120),
            'invalid_payment_item_name',
            DomainFailureKind::Invalid,
        ],
        'a report filtered by a malformed customer' => [
            InvalidPaymentReportFilter::malformedCustomer('42'),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report filtered by too many customers' => [
            InvalidPaymentReportFilter::tooManyCustomers(100),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report filtered by a status no sale has' => [
            InvalidPaymentReportFilter::unknownStatus('refunded'),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report filtered by a type no transaction has' => [
            InvalidPaymentReportFilter::unknownTransactionType('chargeback'),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report filtered by a method outside the catalog' => [
            InvalidPaymentReportFilter::unknownPaymentMethod('bitcoin'),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report filtered by a reference longer than any code' => [
            InvalidPaymentReportFilter::referenceTooLong(8),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report sorted by a column it does not serve' => [
            InvalidPaymentReportFilter::unknownSort('business_id'),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report sorted in a direction that does not exist' => [
            InvalidPaymentReportFilter::unknownDirection('sideways'),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report page before the first' => [
            InvalidPaymentReportFilter::pageOutOfRange(0),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report page of more rows than served' => [
            InvalidPaymentReportFilter::perPageOutOfRange(101, 100),
            'invalid_payment_report_filter',
            DomainFailureKind::Invalid,
        ],
        'a report date that is no calendar date' => [
            InvalidPaymentReportPeriod::malformed('2026-02-30'),
            'invalid_payment_report_period',
            DomainFailureKind::Invalid,
        ],
        'a report period missing one of its dates' => [
            InvalidPaymentReportPeriod::incomplete(),
            'invalid_payment_report_period',
            DomainFailureKind::Invalid,
        ],
        'a report period that ends before it starts' => [
            InvalidPaymentReportPeriod::inverted('2026-03-31', '2026-03-01'),
            'invalid_payment_report_period',
            DomainFailureKind::Invalid,
        ],
        'a report period wider than five years' => [
            InvalidPaymentReportPeriod::tooWide('2020-01-01', '2026-01-01', 5),
            'invalid_payment_report_period',
            DomainFailureKind::Invalid,
        ],
        'a transaction of nothing' => [
            InvalidTransactionAmount::notPositive(0),
            'invalid_transaction_amount',
            DomainFailureKind::Invalid,
        ],
        'a transaction past the largest one served' => [
            InvalidTransactionAmount::tooLarge(Money::MAXIMUM_CENTS + 1, Money::MAXIMUM_CENTS),
            'invalid_transaction_amount',
            DomainFailureKind::Invalid,
        ],
        'a void nobody is accountable for' => [
            InvalidVoidActor::empty(),
            'invalid_void_actor',
            DomainFailureKind::Invalid,
        ],
        'a void by a malformed account' => [
            InvalidVoidActor::malformed('42'),
            'invalid_void_actor',
            DomainFailureKind::Invalid,
        ],
        'an account nobody holds' => [
            PaymentAccountNotFound::withId($id),
            'payment_account_not_found',
            DomainFailureKind::NotFound,
        ],
        'a payment with nothing left owing' => [
            PaymentAlreadySettled::withId($id),
            'payment_already_settled',
            DomainFailureKind::Conflict,
        ],
        'a payment that already holds money' => [
            PaymentAlreadyStarted::withId($id),
            'payment_already_started',
            DomainFailureKind::Conflict,
        ],
        'an appointment of another business' => [
            PaymentAppointmentNotFound::withId($id),
            'payment_appointment_not_found',
            DomainFailureKind::NotFound,
        ],
        'a malformed appointment identifier' => [
            PaymentAppointmentNotFound::malformed('42'),
            'payment_appointment_not_found',
            DomainFailureKind::NotFound,
        ],
        'a business that is gone' => [
            PaymentBusinessNotFound::withId($id),
            'payment_business_not_found',
            DomainFailureKind::NotFound,
        ],
        'a method the business does not accept' => [
            PaymentMethodNotEnabled::withId($id),
            'payment_method_not_enabled',
            DomainFailureKind::Invalid,
        ],
        'a method nobody offers' => [
            PaymentMethodNotFound::withId($id),
            'payment_method_not_found',
            DomainFailureKind::NotFound,
        ],
        'a payment that is not there' => [
            PaymentNotFound::withId($id),
            'payment_not_found',
            DomainFailureKind::NotFound,
        ],
        'more money than is owed' => [
            PaymentOverpaid::byCents(10_001, 10_000),
            'payment_overpaid',
            DomainFailureKind::Conflict,
        ],
        'a service of another business' => [
            PaymentServiceNotFound::withId($id),
            'payment_service_not_found',
            DomainFailureKind::NotFound,
        ],
        'a transaction that is not there' => [
            PaymentTransactionNotFound::withId($id),
            'payment_transaction_not_found',
            DomainFailureKind::NotFound,
        ],
        'a ledger row that is no approved charge' => [
            PaymentTransactionNotVoidable::withId($id),
            'payment_transaction_not_voidable',
            DomainFailureKind::Conflict,
        ],
        'more items than a payment holds' => [
            TooManyPaymentItems::atMost(20),
            'too_many_payment_items',
            DomainFailureKind::Invalid,
        ],
        'a void bigger than what is still collected' => [
            VoidExceedsPaidAmount::byCents(6_000, 4_000),
            'void_exceeds_paid_amount',
            DomainFailureKind::Conflict,
        ],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (
    DomainFailure $failure,
    string $code,
) {
    expect($failure->errorCode())->toBe($code);
})->with(paymentFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(paymentFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(paymentFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(paymentFailures())->with(['en', 'es']);

it('says what it turned down without naming another business row', function () {
    expect(PaymentNotFound::withId('the-id')->getMessage())
        ->toBe('Payment [the-id] was not found.')
        ->and(PaymentOverpaid::byCents(10_001, 10_000)->getMessage())
        ->toBe('A payment of [10001] exceeds the outstanding balance of [10000].')
        ->and(PaymentAlreadyStarted::withId('the-id')->getMessage())
        ->toBe('Payment [the-id] already holds money and can no longer be changed.')
        ->and(DiscountExceedsSubtotal::of(11_001, 11_000)->getMessage())
        ->toBe('A discount of [11001] exceeds the subtotal of [11000].')
        ->and(PaymentTransactionNotVoidable::withId('the-id')->getMessage())
        ->toBe('Payment transaction [the-id] is not an approved charge.')
        ->and(VoidExceedsPaidAmount::byCents(6_000, 4_000)->getMessage())
        ->toBe('A void of [6000] exceeds the collected amount of [4000].');
});

it('says which report filter it turned down', function () {
    expect(InvalidPaymentReportFilter::malformedCustomer('42')->getMessage())
        ->toBe('The customer filter [42] is not a well-formed identifier.')
        ->and(InvalidPaymentReportFilter::tooManyCustomers(100)->getMessage())
        ->toBe('A payment report may filter by at most 100 customers.')
        ->and(InvalidPaymentReportFilter::perPageOutOfRange(101, 100)->getMessage())
        ->toBe('A report page holds between 1 and 100 rows, got [101].')
        ->and(InvalidPaymentReportPeriod::inverted('2026-03-31', '2026-03-01')->getMessage())
        ->toBe('A report period has to start on or before it ends, got [2026-03-31] to [2026-03-01].');
});

/**
 * @return list<string>
 */
function declaredPaymentFailures(): array
{
    return array_map(
        fn (string $path) => basename($path, '.php'),
        glob(dirname(__DIR__, 5).'/app/Domains/Payments/Exceptions/*.php') ?: [],
    );
}

it('gives each refusal an error code of its own', function () {
    $codes = array_map(
        fn (array $failure) => $failure[0]->errorCode(),
        array_values(paymentFailures()),
    );

    expect(array_unique($codes))->toHaveCount(count(declaredPaymentFailures()));
});

it('covers every refusal the domain declares', function () {
    $covered = array_map(
        fn (array $failure) => (new ReflectionClass($failure[0]))->getShortName(),
        array_values(paymentFailures()),
    );

    expect(array_values(array_unique($covered)))->toEqualCanonicalizing(declaredPaymentFailures());
});

it('keeps the cause of a conflict the database raised', function () {
    $cause = new RuntimeException('SQLSTATE[23505]');

    expect(AppointmentAlreadyHasPayment::forAppointment('the-id', $cause)->getPrevious())->toBe($cause);
});
