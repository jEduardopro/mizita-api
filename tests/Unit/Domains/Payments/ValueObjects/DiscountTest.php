<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\DiscountExceedsSubtotal;
use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\Money;
use App\Shared\ValueObjects\CurrencyCode;

function discountSubtotal(int $cents): Money
{
    return Money::fromCents($cents, CurrencyCode::default());
}

describe('no discount', function () {
    it('takes nothing off any subtotal', function () {
        expect(Discount::none()->amountOf(discountSubtotal(11000))->amount)->toBe(0);
    });

    it('carries the type and value a stored row holds', function () {
        expect(Discount::none()->type)->toBe(DiscountType::None)
            ->and(Discount::none()->value)->toBe(0)
            ->and(Discount::none()->isNone())->toBeTrue();
    });
});

describe('a percentage discount', function () {
    it('rounds a fraction of a cent half up, the same way the front end does', function (
        int $basisPoints,
        int $subtotal,
        int $discount,
    ) {
        expect(Discount::ofPercentage($basisPoints)->amountOf(discountSubtotal($subtotal))->amount)->toBe($discount);
    })->with([
        'four point five five percent of 110.00 is 5.01, not 5.00' => [455, 11000, 501],
        'fifteen point five percent of 100.00' => [1550, 10000, 1550],
        'nothing off' => [0, 10000, 0],
        'everything off' => [10000, 10000, 10000],
        'exactly half a cent rounds up' => [5000, 1, 1],
        'one and a half cents rounds up' => [5000, 3, 2],
        'just under half a cent rounds down' => [4999, 3, 1],
        'a third of a cent rounds down' => [3333, 1, 0],
        'ten percent of 9.99' => [1000, 999, 100],
        'a third off a round subtotal' => [3333, 10000, 3333],
        'nothing off nothing' => [1550, 0, 0],
    ]);

    it('takes the discount in the currency of the subtotal', function () {
        $discount = Discount::ofPercentage(1000)->amountOf(Money::fromCents(10000, CurrencyCode::restore('USD')));

        expect($discount->currency->value)->toBe('USD')
            ->and($discount->amount)->toBe(1000);
    });

    it('accepts the whole range of percentages it serves', function (int $basisPoints) {
        expect(Discount::ofPercentage($basisPoints)->value)->toBe($basisPoints);
    })->with([
        'nothing' => 0,
        'a single basis point' => 1,
        'half' => 5000,
        'the maximum' => Discount::MAXIMUM_BASIS_POINTS,
    ]);

    it('refuses a percentage outside the range it serves', function (int $basisPoints) {
        expect(fn () => Discount::ofPercentage($basisPoints))
            ->toThrow(InvalidPaymentDiscount::class, 'is outside the supported discount percentage range');
    })->with([
        'over one hundred percent' => Discount::MAXIMUM_BASIS_POINTS + 1,
        'far over one hundred percent' => 1_000_000,
        'negative' => -1,
    ]);

    it('is not the absence of a discount even when it takes nothing off', function () {
        expect(Discount::ofPercentage(0)->isNone())->toBeFalse()
            ->and(Discount::ofPercentage(0)->type)->toBe(DiscountType::Percentage);
    });
});

describe('a fixed discount', function () {
    it('takes exactly the amount it carries off the subtotal', function () {
        expect(Discount::ofAmount(2500)->amountOf(discountSubtotal(11000))->amount)->toBe(2500);
    });

    it('may take the whole subtotal', function () {
        expect(Discount::ofAmount(11000)->amountOf(discountSubtotal(11000))->amount)->toBe(11000);
    });

    it('refuses to take more than the subtotal holds', function () {
        expect(fn () => Discount::ofAmount(11001)->amountOf(discountSubtotal(11000)))
            ->toThrow(DiscountExceedsSubtotal::class, 'A discount of [11001] exceeds the subtotal of [11000].');
    });

    it('refuses any amount against an empty subtotal', function () {
        expect(fn () => Discount::ofAmount(1)->amountOf(discountSubtotal(0)))
            ->toThrow(DiscountExceedsSubtotal::class);
    });

    it('refuses a negative amount', function () {
        expect(fn () => Discount::ofAmount(-1))
            ->toThrow(InvalidPaymentDiscount::class, '[-1] is not a valid discount amount.');
    });

    it('takes the discount in the currency of the subtotal', function () {
        $discount = Discount::ofAmount(500)->amountOf(Money::fromCents(10000, CurrencyCode::restore('USD')));

        expect($discount->currency->value)->toBe('USD');
    });

    it('is not the absence of a discount even when it takes nothing off', function () {
        expect(Discount::ofAmount(0)->isNone())->toBeFalse()
            ->and(Discount::ofAmount(0)->type)->toBe(DiscountType::Fixed);
    });
});

it('restores a stored discount verbatim, skipping the range it would refuse', function () {
    $restored = Discount::restore(DiscountType::Percentage, 99_999);

    expect($restored->type)->toBe(DiscountType::Percentage)
        ->and($restored->value)->toBe(99_999);
});
