<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\DiscountInput;
use App\Domains\Payments\Exceptions\InvalidPaymentDiscount;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\DiscountType;

describe('reading an untrusted payload', function () {
    it('accepts the shape a form request would have let through', function () {
        $discount = DiscountInput::fromRequest(['type' => 'percentage', 'value' => 1_500]);

        expect($discount->type)->toBe('percentage')
            ->and($discount->value)->toBe(1_500)
            ->and(fn () => $discount->validate())->not->toThrow(Throwable::class);
    });

    it('survives a payload with no keys at all and refuses it on validate', function () {
        $discount = DiscountInput::fromRequest([]);

        expect($discount->type)->toBe('')
            ->and($discount->value)->toBe(0)
            ->and(fn () => $discount->validate())->toThrow(InvalidPaymentDiscount::class);
    });

    it('survives a payload whose values are the wrong type', function (mixed $type, mixed $value) {
        $discount = DiscountInput::fromRequest(['type' => $type, 'value' => $value]);

        expect($discount->type)->toBeString()
            ->and($discount->value)->toBeInt()
            ->and(fn () => $discount->validate())->toThrow(InvalidPaymentDiscount::class);
    })->with([
        'an array where a type belongs' => [['percentage'], 'nonsense'],
        'a null type and a null value' => [null, null],
        'a boolean type and an object value' => [false, new stdClass],
        'a number where a type belongs' => [10, []],
    ]);

    it('reads a numeric string value as an int', function () {
        expect(DiscountInput::fromRequest(['type' => 'fixed', 'value' => '2500'])->value)->toBe(2_500);
    });
});

describe('the rules it states', function () {
    it('refuses a type outside the catalogue', function (string $type) {
        expect(fn () => (new DiscountInput($type, 0))->validate())
            ->toThrow(InvalidPaymentDiscount::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a plausible synonym' => 'percent',
        'the enum label rather than its value' => 'Percentage',
        'something invented' => 'buy_one_get_one',
    ]);

    it('accepts a percentage at either end of its range', function (int $basisPoints) {
        expect(fn () => (new DiscountInput('percentage', $basisPoints))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'nothing off' => 0,
        'a tenth of a percent' => 10,
        'half off' => 5_000,
        'everything off' => Discount::MAXIMUM_BASIS_POINTS,
    ]);

    it('refuses a percentage outside its range', function (int $basisPoints) {
        expect(fn () => (new DiscountInput('percentage', $basisPoints))->validate())
            ->toThrow(InvalidPaymentDiscount::class);
    })->with([
        'a negative percentage' => -1,
        'one basis point past the whole' => Discount::MAXIMUM_BASIS_POINTS + 1,
        'an absurd percentage' => 1_000_000,
    ]);

    it('accepts a fixed amount of zero or more', function (int $cents) {
        expect(fn () => (new DiscountInput('fixed', $cents))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'nothing off' => 0,
        'a few pesos off' => 2_500,
    ]);

    it('refuses a negative fixed amount', function () {
        expect(fn () => (new DiscountInput('fixed', -1))->validate())
            ->toThrow(InvalidPaymentDiscount::class);
    });

    it('rules on the type before it rules on the value', function () {
        expect(fn () => (new DiscountInput('percent', -1))->validate())
            ->toThrow(InvalidPaymentDiscount::class, '[percent] is not a supported discount type.');
    });
});

describe('turning itself into a discount', function () {
    it('builds the discount its type names', function (string $type, int $value, DiscountType $expected, int $expectedValue) {
        $discount = (new DiscountInput($type, $value))->toDiscount();

        expect($discount)->toBeInstanceOf(Discount::class)
            ->and($discount->type)->toBe($expected)
            ->and($discount->value)->toBe($expectedValue);
    })->with([
        'no discount at all' => ['none', 0, DiscountType::None, 0],
        'a percentage off' => ['percentage', 1_500, DiscountType::Percentage, 1_500],
        'an amount off' => ['fixed', 2_500, DiscountType::Fixed, 2_500],
    ]);

    it('ignores the value attached to a discount of none', function () {
        $discount = (new DiscountInput('none', 9_999))->toDiscount();

        expect($discount->isNone())->toBeTrue()
            ->and($discount->value)->toBe(0);
    });
});
