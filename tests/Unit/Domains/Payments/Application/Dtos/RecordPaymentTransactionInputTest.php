<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\RecordPaymentTransactionInput;
use App\Domains\Payments\Exceptions\InvalidPaymentActor;
use App\Domains\Payments\Exceptions\InvalidTransactionAmount;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\ValueObjects\Money;
use Tests\Support\Payments\PaymentFixtures;

describe('reading an untrusted payload', function () {
    it('assembles itself from the payload a form request would have let through', function () {
        $input = RecordPaymentTransactionInput::fromRequest([
            'payment_method_id' => PaymentFixtures::CARD_METHOD_ID,
            'amount_cents' => 25_000,
        ], PaymentFixtures::PAYMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->paymentId)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($input->paymentMethodId)->toBe(PaymentFixtures::CARD_METHOD_ID)
            ->and($input->amountCents)->toBe(25_000)
            ->and($input->actorAccountId)->toBe(PaymentFixtures::ACTOR_ID)
            ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('takes the payment from the route and never from the body', function () {
        $input = RecordPaymentTransactionInput::fromRequest([
            'payment_id' => PaymentFixtures::UNKNOWN_ID,
            'payment_method_id' => PaymentFixtures::CASH_METHOD_ID,
            'amount_cents' => 1_000,
        ], PaymentFixtures::PAYMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->paymentId)->toBe(PaymentFixtures::PAYMENT_ID);
    });

    it('takes the actor from the authenticated caller and never from the body', function () {
        $input = RecordPaymentTransactionInput::fromRequest([
            'actor_account_id' => PaymentFixtures::OTHER_ACTOR_ID,
            'account_id' => PaymentFixtures::OTHER_ACTOR_ID,
            'payment_method_id' => PaymentFixtures::CASH_METHOD_ID,
            'amount_cents' => 1_000,
        ], PaymentFixtures::PAYMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->actorAccountId)->toBe(PaymentFixtures::ACTOR_ID);
    });

    it('survives a payload with no keys at all and refuses it on validate', function () {
        $input = RecordPaymentTransactionInput::fromRequest([], PaymentFixtures::PAYMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->paymentMethodId)->toBe('')
            ->and($input->amountCents)->toBe(0)
            ->and(fn () => $input->validate())->toThrow(PaymentMethodNotFound::class);
    });

    it('survives a payload whose values are the wrong type', function (mixed $paymentMethodId, mixed $amountCents) {
        $input = RecordPaymentTransactionInput::fromRequest([
            'payment_method_id' => $paymentMethodId,
            'amount_cents' => $amountCents,
        ], PaymentFixtures::PAYMENT_ID, PaymentFixtures::ACTOR_ID);

        expect($input->paymentMethodId)->toBeString()
            ->and($input->amountCents)->toBeInt()
            ->and(fn () => $input->validate())->toThrow(PaymentMethodNotFound::class);
    })->with([
        'an array where an identifier belongs' => [[PaymentFixtures::CASH_METHOD_ID], 'nonsense'],
        'nulls throughout' => [null, null],
        'a boolean and an object' => [true, new stdClass],
    ]);

    it('reads a numeric string amount as cents', function () {
        expect(RecordPaymentTransactionInput::fromRequest([
            'payment_method_id' => PaymentFixtures::CASH_METHOD_ID,
            'amount_cents' => '25000',
        ], PaymentFixtures::PAYMENT_ID, PaymentFixtures::ACTOR_ID)->amountCents)->toBe(25_000);
    });
});

describe('the rules it states', function () {
    it('refuses a payment identifier that is no uuid', function (string $paymentId) {
        expect(fn () => PaymentFixtures::recordInput(paymentId: $paymentId)->validate())
            ->toThrow(PaymentNotFound::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '9',
        'a word' => 'not-a-uuid',
    ]);

    it('refuses a payment method identifier that is no uuid', function (string $paymentMethodId) {
        expect(fn () => PaymentFixtures::recordInput(paymentMethodId: $paymentMethodId)->validate())
            ->toThrow(PaymentMethodNotFound::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '2',
        'a word' => 'card',
    ]);

    it('refuses a transaction of nothing', function (int $amountCents) {
        expect(fn () => PaymentFixtures::recordInput(amountCents: $amountCents)->validate())
            ->toThrow(InvalidTransactionAmount::class);
    })->with([
        'no money at all' => 0,
        'a negative amount' => -1,
        'a deeply negative amount' => -25_000,
    ]);

    it('refuses a transaction past the bounds money can hold', function () {
        expect(fn () => PaymentFixtures::recordInput(amountCents: Money::MAXIMUM_CENTS + 1)->validate())
            ->toThrow(InvalidTransactionAmount::class);
    });

    it('accepts a transaction at either end of the range money allows', function (int $amountCents) {
        expect(fn () => PaymentFixtures::recordInput(amountCents: $amountCents)->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a single cent' => 1,
        'the maximum money can hold' => Money::MAXIMUM_CENTS,
    ]);

    it('refuses an actor identifier that is no uuid', function (string $actorAccountId) {
        expect(fn () => PaymentFixtures::recordInput(actorAccountId: $actorAccountId)->validate())
            ->toThrow(InvalidPaymentActor::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '4',
        'a word' => 'the-owner',
    ]);

    it('rules on the payment before the payment method', function () {
        expect(fn () => PaymentFixtures::recordInput(paymentId: 'nope', paymentMethodId: 'nope')->validate())
            ->toThrow(PaymentNotFound::class);
    });

    it('rules on the amount before the actor', function () {
        expect(fn () => PaymentFixtures::recordInput(amountCents: 0, actorAccountId: 'nope')->validate())
            ->toThrow(InvalidTransactionAmount::class);
    });
});
