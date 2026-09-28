<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Exceptions\InvalidSubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CurrencyCode;
use App\Shared\ValueObjects\DomainFailureKind;

it('keeps the amount in minor units and the currency', function () {
    $price = SubscriptionPrice::of(19950, CurrencyCode::fromString('USD'));

    expect($price->amountInMinorUnits)->toBe(19950)
        ->and($price->currency->value)->toBe('USD');
});

it('accepts a zero price, so a complimentary grant is expressible', function () {
    expect(SubscriptionPrice::of(0, CurrencyCode::default())->amountInMinorUnits)->toBe(0);
});

it('rejects a negative amount', function (int $amount) {
    expect(fn () => SubscriptionPrice::of($amount, CurrencyCode::default()))
        ->toThrow(InvalidSubscriptionPrice::class);
})->with(['minus one' => -1, 'minus a list price' => -20000, 'the smallest int' => PHP_INT_MIN]);

it('refuses a negative amount as an invalid domain failure', function () {
    try {
        SubscriptionPrice::of(-1, CurrencyCode::default());
    } catch (InvalidSubscriptionPrice $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('invalid_subscription_price')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);

        return;
    }

    $this->fail('A negative price was accepted.');
});

it('changes the amount and keeps the currency', function () {
    $price = SubscriptionPrice::of(20000, CurrencyCode::fromString('EUR'))->withAmount(15000);

    expect($price->amountInMinorUnits)->toBe(15000)
        ->and($price->currency->value)->toBe('EUR');
});

it('leaves the original price untouched when the amount changes', function () {
    $original = SubscriptionPrice::of(20000, CurrencyCode::default());

    $original->withAmount(0);

    expect($original->amountInMinorUnits)->toBe(20000);
});

it('holds a changed amount to the same rule as a new price', function () {
    expect(fn () => SubscriptionPrice::of(20000, CurrencyCode::default())->withAmount(-5))
        ->toThrow(InvalidSubscriptionPrice::class);
});

it('restores a stored amount without holding it to the creation rule', function () {
    expect(SubscriptionPrice::restore(-1, CurrencyCode::restore('mxn'))->amountInMinorUnits)->toBe(-1);
});
