<?php

declare(strict_types=1);

use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentTransactionAlreadyVoided;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;
use App\Shared\ValueObjects\CurrencyCode;

const TRANSACTION_ID = '01930000-0000-7000-8000-000000000101';

const TRANSACTION_METHOD_ID = '01930000-0000-7000-8000-000000000102';

const TRANSACTION_ACTOR_ID = '01930000-0000-7000-8000-000000000103';

function recordedTransaction(int $cents = 4000): PaymentTransaction
{
    return PaymentTransaction::record(
        TRANSACTION_ID,
        TRANSACTION_METHOD_ID,
        Money::fromCents($cents, CurrencyCode::default()),
        new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
    );
}

describe('a transaction just recorded', function () {
    it('carries every identity as the uuid the api speaks in', function () {
        expect(recordedTransaction()->id)->toBe(TRANSACTION_ID)
            ->and(recordedTransaction()->paymentMethodId)->toBe(TRANSACTION_METHOD_ID);
    });

    it('keeps the amount and the instant it was processed at', function () {
        $transaction = recordedTransaction(4000);

        expect($transaction->amount->amount)->toBe(4000)
            ->and($transaction->amount->currency->value)->toBe('MXN')
            ->and($transaction->processedAt)->toEqual(new DateTimeImmutable('2026-03-09T12:00:00+00:00'));
    });

    it('starts completed, with nobody having voided it', function () {
        $transaction = recordedTransaction();

        expect($transaction->isVoided())->toBeFalse()
            ->and($transaction->status())->toBe(PaymentTransactionStatus::Completed)
            ->and($transaction->voidedAt())->toBeNull()
            ->and($transaction->voidedByAccountId())->toBeNull();
    });
});

describe('voiding', function () {
    it('records who voided it and when', function () {
        $transaction = recordedTransaction();
        $voidedAt = new DateTimeImmutable('2026-03-10T08:30:00+00:00');

        $transaction->void(TRANSACTION_ACTOR_ID, $voidedAt);

        expect($transaction->isVoided())->toBeTrue()
            ->and($transaction->status())->toBe(PaymentTransactionStatus::Voided)
            ->and($transaction->voidedAt())->toEqual($voidedAt)
            ->and($transaction->voidedByAccountId())->toBe(TRANSACTION_ACTOR_ID);
    });

    it('leaves the amount and the original instant untouched', function () {
        $transaction = recordedTransaction(4000);

        $transaction->void(TRANSACTION_ACTOR_ID, new DateTimeImmutable('2026-03-10T08:30:00+00:00'));

        expect($transaction->amount->amount)->toBe(4000)
            ->and($transaction->processedAt)->toEqual(new DateTimeImmutable('2026-03-09T12:00:00+00:00'));
    });

    it('refuses a second void', function () {
        $transaction = recordedTransaction();
        $transaction->void(TRANSACTION_ACTOR_ID, new DateTimeImmutable('2026-03-10T08:30:00+00:00'));

        expect(fn () => $transaction->void(TRANSACTION_ACTOR_ID, new DateTimeImmutable('2026-03-11T08:30:00+00:00')))
            ->toThrow(
                PaymentTransactionAlreadyVoided::class,
                'Payment transaction ['.TRANSACTION_ID.'] is already voided.',
            );
    });

    it('keeps the first void when a second one is turned down', function () {
        $transaction = recordedTransaction();
        $firstVoid = new DateTimeImmutable('2026-03-10T08:30:00+00:00');
        $transaction->void(TRANSACTION_ACTOR_ID, $firstVoid);

        $secondVoid = new DateTimeImmutable('2026-03-11T08:30:00+00:00');

        try {
            $transaction->void('01930000-0000-7000-8000-000000000999', $secondVoid);
        } catch (PaymentTransactionAlreadyVoided) {
        }

        expect($transaction->voidedAt())->toEqual($firstVoid)
            ->and($transaction->voidedByAccountId())->toBe(TRANSACTION_ACTOR_ID);
    });

    it('refuses a void nobody is accountable for', function (string $actor) {
        $transaction = recordedTransaction();

        expect(fn () => $transaction->void($actor, new DateTimeImmutable('2026-03-10T08:30:00+00:00')))
            ->toThrow(
                InvalidVoidActor::class,
                'A payment transaction can only be voided by an identified account.',
            );

        expect($transaction->isVoided())->toBeFalse()
            ->and($transaction->voidedAt())->toBeNull()
            ->and($transaction->voidedByAccountId())->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
        'a newline' => "\n",
    ]);

    it('turns down a second void before it asks who is voiding', function () {
        $transaction = recordedTransaction();
        $transaction->void(TRANSACTION_ACTOR_ID, new DateTimeImmutable('2026-03-10T08:30:00+00:00'));

        expect(fn () => $transaction->void('', new DateTimeImmutable('2026-03-11T08:30:00+00:00')))
            ->toThrow(PaymentTransactionAlreadyVoided::class);
    });
});

it('restores a voided transaction with the void it was stored with', function () {
    $transaction = PaymentTransaction::restore(
        TRANSACTION_ID,
        TRANSACTION_METHOD_ID,
        Money::fromCents(4000, CurrencyCode::default()),
        new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
        new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
        TRANSACTION_ACTOR_ID,
    );

    expect($transaction->isVoided())->toBeTrue()
        ->and($transaction->status())->toBe(PaymentTransactionStatus::Voided)
        ->and($transaction->voidedByAccountId())->toBe(TRANSACTION_ACTOR_ID);
});

it('restores a live transaction with no void on it', function () {
    $transaction = PaymentTransaction::restore(
        TRANSACTION_ID,
        TRANSACTION_METHOD_ID,
        Money::fromCents(4000, CurrencyCode::default()),
        new DateTimeImmutable('2026-03-09T12:00:00+00:00'),
        null,
        null,
    );

    expect($transaction->isVoided())->toBeFalse()
        ->and($transaction->status())->toBe(PaymentTransactionStatus::Completed);
});
