<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\AppointmentAlreadyHasPayment;
use App\Domains\Payments\Exceptions\CurrencyMismatch;
use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Domains\Payments\Exceptions\InvalidMoneyAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentAlreadySettled;
use App\Domains\Payments\Exceptions\PaymentAlreadyStarted;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentBusinessNotFound;
use App\Domains\Payments\Exceptions\PaymentMethodNotEnabled;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\Exceptions\PaymentOverpaid;
use App\Domains\Payments\Exceptions\PaymentServiceNotFound;
use App\Domains\Payments\Exceptions\PaymentTransactionAlreadyVoided;
use App\Domains\Payments\Exceptions\PaymentTransactionNotFound;
use App\Domains\Payments\Exceptions\TooManyPaymentItems;
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
        'a transaction already voided' => [
            PaymentTransactionAlreadyVoided::withId($id),
            'payment_transaction_already_voided',
            DomainFailureKind::Conflict,
        ],
        'a transaction that is not there' => [
            PaymentTransactionNotFound::withId($id),
            'payment_transaction_not_found',
            DomainFailureKind::NotFound,
        ],
        'more items than a payment holds' => [
            TooManyPaymentItems::atMost(20),
            'too_many_payment_items',
            DomainFailureKind::Invalid,
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
        ->toBe('A discount of [11001] exceeds the subtotal of [11000].');
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
