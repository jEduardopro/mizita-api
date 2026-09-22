<?php

declare(strict_types=1);

use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentItem;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\Exceptions\CurrencyMismatch;
use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentAlreadySettled;
use App\Domains\Payments\Exceptions\PaymentAlreadyStarted;
use App\Domains\Payments\Exceptions\PaymentOverpaid;
use App\Domains\Payments\Exceptions\PaymentTransactionAlreadyVoided;
use App\Domains\Payments\Exceptions\PaymentTransactionNotFound;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use App\Shared\ValueObjects\CurrencyCode;

const PAYMENT_AGGREGATE_ID = '01930000-0000-7000-8000-000000000001';

const PAYMENT_AGGREGATE_BUSINESS_ID = '01930000-0000-7000-8000-000000000002';

const PAYMENT_AGGREGATE_APPOINTMENT_ID = '01930000-0000-7000-8000-000000000003';

const PAYMENT_AGGREGATE_METHOD_ID = '01930000-0000-7000-8000-000000000004';

const PAYMENT_AGGREGATE_ACTOR_ID = '01930000-0000-7000-8000-000000000005';

function paymentAggregateMoney(int $cents): Money
{
    return Money::fromCents($cents, CurrencyCode::default());
}

function paymentAggregateForeignMoney(int $cents): Money
{
    return Money::fromCents($cents, CurrencyCode::restore('USD'));
}

function paymentAggregateInstant(string $value = '2026-03-09T12:00:00+00:00'): DateTimeImmutable
{
    return new DateTimeImmutable($value);
}

function paymentAggregateChildId(int $position): string
{
    return sprintf('01930000-0000-7000-8000-00000000%04d', 1000 + $position);
}

function openPaymentAggregate(): Payment
{
    return Payment::open(
        id: PAYMENT_AGGREGATE_ID,
        businessId: PAYMENT_AGGREGATE_BUSINESS_ID,
        appointmentId: PAYMENT_AGGREGATE_APPOINTMENT_ID,
        currency: CurrencyCode::default(),
        now: paymentAggregateInstant(),
    );
}

function paymentAggregateWithItems(int ...$amounts): Payment
{
    $payment = openPaymentAggregate();

    foreach ($amounts as $position => $amount) {
        $payment->addItem(
            paymentAggregateChildId($position),
            PaymentItemName::fromString("Item {$position}"),
            paymentAggregateMoney($amount),
        );
    }

    return $payment;
}

function recordOnPaymentAggregate(Payment $payment, int $cents, int $position = 0): PaymentTransaction
{
    return $payment->recordTransaction(
        paymentAggregateChildId(100 + $position),
        PAYMENT_AGGREGATE_METHOD_ID,
        paymentAggregateMoney($cents),
        paymentAggregateInstant(),
    );
}

describe('a payment just opened', function () {
    beforeEach(function () {
        $this->payment = openPaymentAggregate();
    });

    it('carries every identity as the uuid the api speaks in', function () {
        expect($this->payment->id)->toBe(PAYMENT_AGGREGATE_ID)
            ->and($this->payment->businessId)->toBe(PAYMENT_AGGREGATE_BUSINESS_ID)
            ->and($this->payment->appointmentId)->toBe(PAYMENT_AGGREGATE_APPOINTMENT_ID)
            ->and($this->payment->createdAt)->toEqual(paymentAggregateInstant());
    });

    it('opens empty, in the currency it was opened with, owing nothing', function () {
        expect($this->payment->items())->toBe([])
            ->and($this->payment->transactions())->toBe([])
            ->and($this->payment->currency()->value)->toBe('MXN')
            ->and($this->payment->discount()->isNone())->toBeTrue();
    });

    it('reads a payment with nothing on it as already paid rather than pending', function () {
        expect($this->payment->total()->amount)->toBe(0)
            ->and($this->payment->paid()->amount)->toBe(0)
            ->and($this->payment->balance()->amount)->toBe(0)
            ->and($this->payment->status())->toBe(PaymentStatus::Paid)
            ->and($this->payment->isSettled())->toBeTrue();
    });
});

describe('the items being charged for', function () {
    it('adds up the items into the subtotal', function () {
        expect(paymentAggregateWithItems(50000, 12500, 1)->subtotal()->amount)->toBe(62501);
    });

    it('appends each item at the next position', function () {
        $items = paymentAggregateWithItems(50000, 12500)->items();

        expect($items)->toHaveCount(2)
            ->and($items[0]->position)->toBe(0)
            ->and($items[1]->position)->toBe(1)
            ->and($items[0]->id)->toBe(paymentAggregateChildId(0))
            ->and($items[1]->id)->toBe(paymentAggregateChildId(1));
    });

    it('keeps the name and the amount the item was added with', function () {
        $payment = openPaymentAggregate();

        $payment->addItem(
            paymentAggregateChildId(0),
            PaymentItemName::fromString('  Corte de cabello  '),
            paymentAggregateMoney(35000),
        );

        expect($payment->items()[0]->name->value)->toBe('Corte de cabello')
            ->and($payment->items()[0]->amount->amount)->toBe(35000)
            ->and($payment->items()[0]->amount->currency->value)->toBe('MXN');
    });

    it('refuses an item in a currency the payment does not hold, and keeps it off', function () {
        $payment = paymentAggregateWithItems(50000);

        expect(fn () => $payment->addItem(
            paymentAggregateChildId(1),
            PaymentItemName::fromString('Tinte'),
            paymentAggregateForeignMoney(20000),
        ))->toThrow(CurrencyMismatch::class, 'Expected currency [MXN] but received [USD].');

        expect($payment->items())->toHaveCount(1)
            ->and($payment->subtotal()->amount)->toBe(50000);
    });
});

describe('the discount', function () {
    it('takes a percentage off the subtotal to reach the total', function () {
        $payment = paymentAggregateWithItems(11000);

        $payment->applyDiscount(Discount::ofPercentage(455));

        expect($payment->subtotal()->amount)->toBe(11000)
            ->and($payment->discountAmount()->amount)->toBe(501)
            ->and($payment->total()->amount)->toBe(10499);
    });

    it('takes a fixed amount off the subtotal to reach the total', function () {
        $payment = paymentAggregateWithItems(11000);

        $payment->applyDiscount(Discount::ofAmount(2500));

        expect($payment->discount()->type)->toBe(DiscountType::Fixed)
            ->and($payment->discountAmount()->amount)->toBe(2500)
            ->and($payment->total()->amount)->toBe(8500);
    });

    it('leaves the total equal to the subtotal while no discount is applied', function () {
        $payment = paymentAggregateWithItems(11000);

        expect($payment->discountAmount()->amount)->toBe(0)
            ->and($payment->total()->amount)->toBe($payment->subtotal()->amount);
    });

    it('refuses a fixed discount larger than the subtotal and keeps the one already applied', function () {
        $payment = paymentAggregateWithItems(11000);
        $payment->applyDiscount(Discount::ofAmount(1000));

        expect(fn () => $payment->applyDiscount(Discount::ofAmount(11001)))
            ->toThrow(DiscountExceedsSubtotal::class);

        expect($payment->discountAmount()->amount)->toBe(1000)
            ->and($payment->total()->amount)->toBe(10000);
    });

    it('recomputes a percentage discount when an item is added after it', function () {
        $payment = paymentAggregateWithItems(10000);
        $payment->applyDiscount(Discount::ofPercentage(1000));

        $payment->addItem(
            paymentAggregateChildId(1),
            PaymentItemName::fromString('Tinte'),
            paymentAggregateMoney(10000),
        );

        expect($payment->discountAmount()->amount)->toBe(2000)
            ->and($payment->total()->amount)->toBe(18000);
    });
});

describe('recording a transaction', function () {
    it('hands back the transaction it recorded and keeps it on the payment', function () {
        $payment = paymentAggregateWithItems(10000);

        $transaction = recordOnPaymentAggregate($payment, 4000);

        expect($payment->transactions())->toHaveCount(1)
            ->and($payment->transactions()[0])->toBe($transaction)
            ->and($transaction->id)->toBe(paymentAggregateChildId(100))
            ->and($transaction->paymentMethodId)->toBe(PAYMENT_AGGREGATE_METHOD_ID)
            ->and($transaction->amount->amount)->toBe(4000)
            ->and($transaction->processedAt)->toEqual(paymentAggregateInstant())
            ->and($transaction->status())->toBe(PaymentTransactionStatus::Completed);
    });

    it('moves what is paid and what is left owing', function () {
        $payment = paymentAggregateWithItems(10000);

        recordOnPaymentAggregate($payment, 4000);

        expect($payment->paid()->amount)->toBe(4000)
            ->and($payment->balance()->amount)->toBe(6000)
            ->and($payment->status())->toBe(PaymentStatus::PartiallyPaid)
            ->and($payment->isSettled())->toBeFalse();
    });

    it('settles the payment when the balance is paid in full', function () {
        $payment = paymentAggregateWithItems(10000);

        recordOnPaymentAggregate($payment, 10000);

        expect($payment->balance()->amount)->toBe(0)
            ->and($payment->status())->toBe(PaymentStatus::Paid)
            ->and($payment->isSettled())->toBeTrue();
    });

    it('settles the payment across several transactions', function () {
        $payment = paymentAggregateWithItems(10000);

        recordOnPaymentAggregate($payment, 6000, 0);
        recordOnPaymentAggregate($payment, 4000, 1);

        expect($payment->paid()->amount)->toBe(10000)
            ->and($payment->transactions())->toHaveCount(2)
            ->and($payment->status())->toBe(PaymentStatus::Paid);
    });

    it('charges against the discounted total, not the subtotal', function () {
        $payment = paymentAggregateWithItems(10000);
        $payment->applyDiscount(Discount::ofPercentage(1000));

        recordOnPaymentAggregate($payment, 9000);

        expect($payment->balance()->amount)->toBe(0)
            ->and($payment->status())->toBe(PaymentStatus::Paid);
    });

    it('refuses an amount above the outstanding balance and records nothing', function () {
        $payment = paymentAggregateWithItems(10000);

        expect(fn () => recordOnPaymentAggregate($payment, 10001))
            ->toThrow(PaymentOverpaid::class, 'A payment of [10001] exceeds the outstanding balance of [10000].');

        expect($payment->transactions())->toBe([])
            ->and($payment->paid()->amount)->toBe(0);
    });

    it('refuses a second transaction larger than what is still owed', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 6000, 0);

        expect(fn () => recordOnPaymentAggregate($payment, 4001, 1))
            ->toThrow(PaymentOverpaid::class);

        expect($payment->transactions())->toHaveCount(1)
            ->and($payment->paid()->amount)->toBe(6000);
    });

    it('refuses a transaction of nothing', function () {
        $payment = paymentAggregateWithItems(10000);

        expect(fn () => recordOnPaymentAggregate($payment, 0))
            ->toThrow(InvalidTransactionAmount::class, '[0] is not a valid transaction amount.');

        expect($payment->transactions())->toBe([]);
    });

    it('refuses a transaction in a currency the payment does not hold', function () {
        $payment = paymentAggregateWithItems(10000);

        expect(fn () => $payment->recordTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_METHOD_ID,
            paymentAggregateForeignMoney(1000),
            paymentAggregateInstant(),
        ))->toThrow(CurrencyMismatch::class);

        expect($payment->transactions())->toBe([]);
    });

    it('refuses another transaction once nothing is owed', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 10000, 0);

        expect(fn () => recordOnPaymentAggregate($payment, 1, 1))
            ->toThrow(PaymentAlreadySettled::class, 'Payment ['.PAYMENT_AGGREGATE_ID.'] is already settled.');

        expect($payment->transactions())->toHaveCount(1);
    });

    it('refuses a transaction against a payment with nothing to charge for', function () {
        $payment = openPaymentAggregate();

        expect(fn () => recordOnPaymentAggregate($payment, 100))
            ->toThrow(PaymentAlreadySettled::class);
    });
});

describe('the guard that keeps a started payment from being rewritten', function () {
    it('refuses a new item once money has come in, and keeps it off', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 4000);

        expect(fn () => $payment->addItem(
            paymentAggregateChildId(1),
            PaymentItemName::fromString('Tinte'),
            paymentAggregateMoney(5000),
        ))->toThrow(
            PaymentAlreadyStarted::class,
            'Payment ['.PAYMENT_AGGREGATE_ID.'] already holds money and can no longer be changed.',
        );

        expect($payment->items())->toHaveCount(1)
            ->and($payment->subtotal()->amount)->toBe(10000);
    });

    it('refuses a discount once money has come in, and keeps the total where it was', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 4000);

        expect(fn () => $payment->applyDiscount(Discount::ofPercentage(5000)))
            ->toThrow(PaymentAlreadyStarted::class);

        expect($payment->discount()->isNone())->toBeTrue()
            ->and($payment->total()->amount)->toBe(10000)
            ->and($payment->balance()->amount)->toBe(6000);
    });

    it('closes the payment to changes on the very first cent', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 1);

        expect(fn () => $payment->applyDiscount(Discount::ofAmount(1)))
            ->toThrow(PaymentAlreadyStarted::class);
    });

    it('does not count a voided transaction as money the payment holds', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 4000);
        $payment->voidTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        );

        $payment->addItem(
            paymentAggregateChildId(1),
            PaymentItemName::fromString('Tinte'),
            paymentAggregateMoney(5000),
        );

        expect($payment->subtotal()->amount)->toBe(15000);
    });
});

describe('voiding a transaction', function () {
    beforeEach(function () {
        $this->payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($this->payment, 4000, 0);
    });

    it('keeps the voided transaction on the payment instead of removing it', function () {
        $this->payment->voidTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant('2026-03-10T08:00:00+00:00'),
        );

        $transaction = $this->payment->transactions()[0];

        expect($this->payment->transactions())->toHaveCount(1)
            ->and($transaction->id)->toBe(paymentAggregateChildId(100))
            ->and($transaction->isVoided())->toBeTrue()
            ->and($transaction->status())->toBe(PaymentTransactionStatus::Voided)
            ->and($transaction->voidedAt())->toEqual(paymentAggregateInstant('2026-03-10T08:00:00+00:00'))
            ->and($transaction->voidedByAccountId())->toBe(PAYMENT_AGGREGATE_ACTOR_ID);
    });

    it('gives the money back to the outstanding balance', function () {
        $this->payment->voidTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        );

        expect($this->payment->paid()->amount)->toBe(0)
            ->and($this->payment->balance()->amount)->toBe(10000)
            ->and($this->payment->status())->toBe(PaymentStatus::Pending)
            ->and($this->payment->isSettled())->toBeFalse();
    });

    it('walks the status back from paid to partially paid', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 6000, 0);
        recordOnPaymentAggregate($payment, 4000, 1);

        expect($payment->status())->toBe(PaymentStatus::Paid);

        $payment->voidTransaction(
            paymentAggregateChildId(101),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        );

        expect($payment->status())->toBe(PaymentStatus::PartiallyPaid)
            ->and($payment->paid()->amount)->toBe(6000)
            ->and($payment->balance()->amount)->toBe(4000)
            ->and($payment->transactions())->toHaveCount(2);
    });

    it('refuses to void the same transaction twice', function () {
        $this->payment->voidTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        );

        expect(fn () => $this->payment->voidTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        ))->toThrow(
            PaymentTransactionAlreadyVoided::class,
            'Payment transaction ['.paymentAggregateChildId(100).'] is already voided.',
        );
    });

    it('refuses to void a transaction this payment never recorded', function () {
        expect(fn () => $this->payment->voidTransaction(
            '01930000-0000-7000-8000-000000009999',
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        ))->toThrow(
            PaymentTransactionNotFound::class,
            'Payment transaction [01930000-0000-7000-8000-000000009999] was not found.',
        );

        expect($this->payment->paid()->amount)->toBe(4000);
    });

    it('refuses a void nobody is accountable for, and leaves the money where it was', function () {
        expect(fn () => $this->payment->voidTransaction(
            paymentAggregateChildId(100),
            '   ',
            paymentAggregateInstant(),
        ))->toThrow(InvalidVoidActor::class);

        expect($this->payment->transactions()[0]->isVoided())->toBeFalse()
            ->and($this->payment->paid()->amount)->toBe(4000);
    });

    it('reopens a settled payment for changes once its only transaction is voided', function () {
        $payment = paymentAggregateWithItems(10000);
        recordOnPaymentAggregate($payment, 10000, 0);

        $payment->voidTransaction(
            paymentAggregateChildId(100),
            PAYMENT_AGGREGATE_ACTOR_ID,
            paymentAggregateInstant(),
        );

        $payment->addItem(
            paymentAggregateChildId(1),
            PaymentItemName::fromString('Tinte'),
            paymentAggregateMoney(5000),
        );
        $payment->applyDiscount(Discount::ofAmount(1000));

        expect($payment->subtotal()->amount)->toBe(15000)
            ->and($payment->total()->amount)->toBe(14000)
            ->and($payment->balance()->amount)->toBe(14000)
            ->and($payment->status())->toBe(PaymentStatus::Pending);
    });
});

describe('restoring from persistence', function () {
    it('derives the same totals from the stored rows', function () {
        $payment = Payment::restore(
            id: PAYMENT_AGGREGATE_ID,
            businessId: PAYMENT_AGGREGATE_BUSINESS_ID,
            appointmentId: PAYMENT_AGGREGATE_APPOINTMENT_ID,
            currency: CurrencyCode::default(),
            items: [
                PaymentItem::restore(
                    paymentAggregateChildId(0),
                    PaymentItemName::restore('Corte'),
                    paymentAggregateMoney(10000),
                    0,
                ),
                PaymentItem::restore(
                    paymentAggregateChildId(1),
                    PaymentItemName::restore('Tinte'),
                    paymentAggregateMoney(5000),
                    1,
                ),
            ],
            discount: Discount::restore(DiscountType::Percentage, 1000),
            transactions: [
                PaymentTransaction::restore(
                    paymentAggregateChildId(100),
                    PAYMENT_AGGREGATE_METHOD_ID,
                    paymentAggregateMoney(3500),
                    paymentAggregateInstant(),
                    null,
                    null,
                ),
            ],
            createdAt: paymentAggregateInstant('2026-01-01T00:00:00+00:00'),
        );

        expect($payment->subtotal()->amount)->toBe(15000)
            ->and($payment->discountAmount()->amount)->toBe(1500)
            ->and($payment->total()->amount)->toBe(13500)
            ->and($payment->paid()->amount)->toBe(3500)
            ->and($payment->balance()->amount)->toBe(10000)
            ->and($payment->status())->toBe(PaymentStatus::PartiallyPaid)
            ->and($payment->createdAt)->toEqual(paymentAggregateInstant('2026-01-01T00:00:00+00:00'));
    });

    it('leaves a stored voided transaction out of what was paid and out of the guard', function () {
        $payment = Payment::restore(
            id: PAYMENT_AGGREGATE_ID,
            businessId: PAYMENT_AGGREGATE_BUSINESS_ID,
            appointmentId: PAYMENT_AGGREGATE_APPOINTMENT_ID,
            currency: CurrencyCode::default(),
            items: [
                PaymentItem::restore(
                    paymentAggregateChildId(0),
                    PaymentItemName::restore('Corte'),
                    paymentAggregateMoney(10000),
                    0,
                ),
            ],
            discount: Discount::none(),
            transactions: [
                PaymentTransaction::restore(
                    paymentAggregateChildId(100),
                    PAYMENT_AGGREGATE_METHOD_ID,
                    paymentAggregateMoney(10000),
                    paymentAggregateInstant(),
                    paymentAggregateInstant('2026-03-10T08:00:00+00:00'),
                    PAYMENT_AGGREGATE_ACTOR_ID,
                ),
            ],
            createdAt: paymentAggregateInstant(),
        );

        $payment->applyDiscount(Discount::ofAmount(1000));

        expect($payment->paid()->amount)->toBe(0)
            ->and($payment->status())->toBe(PaymentStatus::Pending)
            ->and($payment->total()->amount)->toBe(9000)
            ->and($payment->transactions())->toHaveCount(1);
    });
});
