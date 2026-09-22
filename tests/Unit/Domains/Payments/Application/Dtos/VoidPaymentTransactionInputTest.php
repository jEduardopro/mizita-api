<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\VoidPaymentTransactionInput;
use App\Domains\Payments\Exceptions\InvalidVoidActor;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\Exceptions\PaymentTransactionNotFound;
use Tests\Support\Payments\PaymentFixtures;

it('accepts a well formed payment, transaction and actor', function () {
    expect(fn () => PaymentFixtures::voidInput()->validate())->not->toThrow(Throwable::class);
});

it('refuses a payment identifier that is no uuid', function (string $paymentId) {
    expect(fn () => PaymentFixtures::voidInput(paymentId: $paymentId)->validate())
        ->toThrow(PaymentNotFound::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '7',
    'a word' => 'not-a-uuid',
]);

it('refuses a transaction identifier that is no uuid', function (string $transactionId) {
    expect(fn () => PaymentFixtures::voidInput(transactionId: $transactionId)->validate())
        ->toThrow(PaymentTransactionNotFound::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '7',
    'a word' => 'not-a-uuid',
]);

it('refuses an actor identifier that is no uuid', function (string $actorAccountId) {
    expect(fn () => PaymentFixtures::voidInput(actorAccountId: $actorAccountId)->validate())
        ->toThrow(InvalidVoidActor::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'a sequential int' => '7',
    'a word' => 'not-a-uuid',
]);

it('rules on the payment before the transaction and on the transaction before the actor', function () {
    expect(fn () => PaymentFixtures::voidInput(paymentId: 'nope', transactionId: 'nope')->validate())
        ->toThrow(PaymentNotFound::class)
        ->and(fn () => PaymentFixtures::voidInput(transactionId: 'nope', actorAccountId: 'nope')->validate())
        ->toThrow(PaymentTransactionNotFound::class);
});

it('cannot be built from a request payload, so the actor can only come from the authenticated caller', function () {
    expect(method_exists(VoidPaymentTransactionInput::class, 'fromRequest'))->toBeFalse();
});
