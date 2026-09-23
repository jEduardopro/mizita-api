<?php

declare(strict_types=1);

use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentBreakdown;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Shared\ValueObjects\CurrencyCode;

const TRANSACTION_ID = '01930000-0000-7000-8000-000000000101';

const TRANSACTION_METHOD_ID = '01930000-0000-7000-8000-000000000102';

const TRANSACTION_ACTOR_ID = '01930000-0000-7000-8000-000000000103';

function transactionMoney(int $cents): Money
{
    return Money::fromCents($cents, CurrencyCode::default());
}

function transactionBreakdown(int $subtotalPreDiscount = 5000, ?Discount $discount = null): PaymentBreakdown
{
    return PaymentBreakdown::of(transactionMoney($subtotalPreDiscount), $discount ?? Discount::none());
}

function approvedTransaction(int $cents = 4000, ?PaymentBreakdown $breakdown = null): PaymentTransaction
{
    return PaymentTransaction::approve(
        TRANSACTION_ID,
        TRANSACTION_METHOD_ID,
        TRANSACTION_ACTOR_ID,
        $breakdown ?? transactionBreakdown(),
        transactionMoney($cents),
        new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
    );
}

describe('a charge just approved', function () {
    it('carries every identity as the uuid the api speaks in', function () {
        expect(approvedTransaction()->id)->toBe(TRANSACTION_ID)
            ->and(approvedTransaction()->paymentMethodId)->toBe(TRANSACTION_METHOD_ID)
            ->and(approvedTransaction()->accountId)->toBe(TRANSACTION_ACTOR_ID);
    });

    it('is stamped approved', function () {
        expect(approvedTransaction()->type)->toBe(PaymentTransactionType::Approved);
    });

    it('keeps the total and the instant it was processed at', function () {
        $transaction = approvedTransaction(4000);

        expect($transaction->total->amount)->toBe(4000)
            ->and($transaction->total->currency->value)->toBe('MXN')
            ->and($transaction->processedAt)->toEqual(new DateTimeImmutable('2026-03-09T12:00:00+00:00'));
    });

    it('keeps the breakdown the charge was priced from', function () {
        $transaction = approvedTransaction(
            4500,
            transactionBreakdown(5000, Discount::ofPercentage(1000)),
        );

        expect($transaction->breakdown->subtotalPreDiscount->amount)->toBe(5000)
            ->and($transaction->breakdown->discountType())->toBe(DiscountType::Percentage)
            ->and($transaction->breakdown->discountValue())->toBe(1000)
            ->and($transaction->breakdown->discountAmount->amount)->toBe(500)
            ->and($transaction->breakdown->subtotal->amount)->toBe(4500);
    });

    it('accepts a charge nobody was authenticated for', function () {
        $transaction = PaymentTransaction::approve(
            TRANSACTION_ID,
            TRANSACTION_METHOD_ID,
            null,
            transactionBreakdown(),
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
        );

        expect($transaction->accountId)->toBeNull();
    });
});

describe('a void appended to the ledger', function () {
    it('is a row of its own, stamped void, for the amount it reverses', function () {
        $void = PaymentTransaction::void(
            '01930000-0000-7000-8000-000000000201',
            TRANSACTION_METHOD_ID,
            TRANSACTION_ACTOR_ID,
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
        );

        expect($void->id)->toBe('01930000-0000-7000-8000-000000000201')
            ->and($void->type)->toBe(PaymentTransactionType::Void)
            ->and($void->paymentMethodId)->toBe(TRANSACTION_METHOD_ID)
            ->and($void->total->amount)->toBe(4000)
            ->and($void->processedAt)->toEqual(new DateTimeImmutable('2026-03-10T08:30:00+00:00'));
    });

    it('names the account that answers for it', function () {
        $void = PaymentTransaction::void(
            '01930000-0000-7000-8000-000000000201',
            TRANSACTION_METHOD_ID,
            TRANSACTION_ACTOR_ID,
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
        );

        expect($void->accountId)->toBe(TRANSACTION_ACTOR_ID);
    });

    it('carries a breakdown of nothing, because it prices nothing', function () {
        $void = PaymentTransaction::void(
            '01930000-0000-7000-8000-000000000201',
            TRANSACTION_METHOD_ID,
            TRANSACTION_ACTOR_ID,
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
        );

        expect($void->breakdown->subtotalPreDiscount->amount)->toBe(0)
            ->and($void->breakdown->discountAmount->amount)->toBe(0)
            ->and($void->breakdown->subtotal->amount)->toBe(0)
            ->and($void->breakdown->discountType())->toBe(DiscountType::None)
            ->and($void->breakdown->discountValue())->toBe(0);
    });

    it('takes its breakdown currency from the amount it reverses', function () {
        $void = PaymentTransaction::void(
            '01930000-0000-7000-8000-000000000201',
            TRANSACTION_METHOD_ID,
            TRANSACTION_ACTOR_ID,
            Money::fromCents(4000, CurrencyCode::restore('USD')),
            new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
        );

        expect($void->breakdown->subtotal->currency->value)->toBe('USD');
    });

    it('refuses a void nobody is accountable for', function (string $actor) {
        expect(fn () => PaymentTransaction::void(
            '01930000-0000-7000-8000-000000000201',
            TRANSACTION_METHOD_ID,
            $actor,
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
        ))->toThrow(
            InvalidVoidActor::class,
            'A payment transaction can only be voided by an identified account.',
        );
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
        'a newline' => "\n",
    ]);
});

describe('restoring from persistence', function () {
    it('reads the stored type back rather than deriving one', function (PaymentTransactionType $type) {
        $transaction = PaymentTransaction::restore(
            TRANSACTION_ID,
            $type,
            TRANSACTION_METHOD_ID,
            TRANSACTION_ACTOR_ID,
            transactionBreakdown(),
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
        );

        expect($transaction->type)->toBe($type);
    })->with([
        'approved' => PaymentTransactionType::Approved,
        'void' => PaymentTransactionType::Void,
        'refund' => PaymentTransactionType::Refund,
        'failed' => PaymentTransactionType::Failed,
    ]);

    it('skips the actor rule a void is created under', function () {
        $transaction = PaymentTransaction::restore(
            TRANSACTION_ID,
            PaymentTransactionType::Void,
            TRANSACTION_METHOD_ID,
            null,
            PaymentBreakdown::none(CurrencyCode::default()),
            transactionMoney(4000),
            new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
        );

        expect($transaction->accountId)->toBeNull()
            ->and($transaction->type)->toBe(PaymentTransactionType::Void);
    });

    it('restores the breakdown the row was stored with, discount and all', function () {
        $transaction = PaymentTransaction::restore(
            TRANSACTION_ID,
            PaymentTransactionType::Approved,
            TRANSACTION_METHOD_ID,
            TRANSACTION_ACTOR_ID,
            PaymentBreakdown::restore(
                transactionMoney(5000),
                Discount::restore(DiscountType::Fixed, 500),
                transactionMoney(500),
                transactionMoney(4500),
            ),
            transactionMoney(4500),
            new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
        );

        expect($transaction->breakdown->subtotalPreDiscount->amount)->toBe(5000)
            ->and($transaction->breakdown->discountType())->toBe(DiscountType::Fixed)
            ->and($transaction->breakdown->discountValue())->toBe(500)
            ->and($transaction->breakdown->subtotal->amount)->toBe(4500);
    });
});

it('is a ledger entry nothing can rewrite, so it carries no mutable void state', function (string $method) {
    expect(method_exists(PaymentTransaction::class, $method))->toBeFalse();
})->with([
    'the voided flag' => 'isVoided',
    'the status reader' => 'status',
    'the void instant' => 'voidedAt',
    'the void actor' => 'voidedByAccountId',
]);

it('offers void only as a named constructor for a new row', function () {
    $void = new ReflectionMethod(PaymentTransaction::class, 'void');

    expect($void->isStatic())->toBeTrue()
        ->and((string) $void->getReturnType())->toBe(PaymentTransaction::class);
});
