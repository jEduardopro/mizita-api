<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentAddOnInput;
use App\Domains\Payments\Exceptions\InvalidPaymentItemAmount;
use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;

describe('reading an untrusted payload', function () {
    it('accepts the shape a form request would have let through', function () {
        $addOn = PaymentAddOnInput::fromRequest(['name' => 'Beard trim', 'amount_cents' => 12_000]);

        expect($addOn->name)->toBe('Beard trim')
            ->and($addOn->amountCents)->toBe(12_000)
            ->and(fn () => $addOn->validate())->not->toThrow(Throwable::class);
    });

    it('survives a payload with no keys at all and refuses it on validate', function () {
        $addOn = PaymentAddOnInput::fromRequest([]);

        expect($addOn->name)->toBe('')
            ->and($addOn->amountCents)->toBe(0)
            ->and(fn () => $addOn->validate())->toThrow(InvalidPaymentItemName::class);
    });

    it('survives a payload whose values are the wrong type', function (mixed $name, mixed $amount) {
        $addOn = PaymentAddOnInput::fromRequest(['name' => $name, 'amount_cents' => $amount]);

        expect($addOn->name)->toBeString()
            ->and($addOn->amountCents)->toBeInt()
            ->and(fn () => $addOn->validate())->toThrow(InvalidPaymentItemName::class);
    })->with([
        'an array where a name belongs' => [['Beard trim'], 'nonsense'],
        'a null name and a null amount' => [null, null],
        'a boolean name and an object amount' => [true, new stdClass],
        'a number where a name belongs' => [42, []],
    ]);

    it('reads a numeric string amount as cents', function () {
        expect(PaymentAddOnInput::fromRequest(['name' => 'Beard trim', 'amount_cents' => '12000'])->amountCents)
            ->toBe(12_000);
    });
});

describe('the rules it states', function () {
    it('refuses a name that says nothing', function (string $name) {
        expect(fn () => (new PaymentAddOnInput($name, 1_000))->validate())
            ->toThrow(InvalidPaymentItemName::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
        'a newline' => "\n",
    ]);

    it('refuses a name past the maximum length', function () {
        expect(fn () => (new PaymentAddOnInput(str_repeat('a', PaymentItemName::MAXIMUM_LENGTH + 1), 1_000))->validate())
            ->toThrow(InvalidPaymentItemName::class);
    });

    it('accepts a name at exactly the maximum length', function () {
        expect(fn () => (new PaymentAddOnInput(str_repeat('a', PaymentItemName::MAXIMUM_LENGTH), 1_000))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('counts accented characters once, not byte by byte', function () {
        expect(fn () => (new PaymentAddOnInput(str_repeat('á', PaymentItemName::MAXIMUM_LENGTH), 1_000))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts an add-on that costs nothing', function () {
        expect(fn () => (new PaymentAddOnInput('Complimentary wash', 0))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts an amount at exactly the maximum', function () {
        expect(fn () => (new PaymentAddOnInput('Package', Money::MAXIMUM_CENTS))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses an amount outside the bounds money can hold', function (int $amountCents) {
        expect(fn () => (new PaymentAddOnInput('Beard trim', $amountCents))->validate())
            ->toThrow(InvalidPaymentItemAmount::class);
    })->with([
        'a negative amount' => -1,
        'a deeply negative amount' => -50_000,
        'one cent past the maximum' => Money::MAXIMUM_CENTS + 1,
    ]);
});

describe('turning itself into a name', function () {
    it('trims the surrounding whitespace off the name it was handed', function () {
        expect((new PaymentAddOnInput('  Beard trim  ', 1_000))->toName()->value)->toBe('Beard trim');
    });

    it('refuses to name an item nobody described', function () {
        expect(fn () => (new PaymentAddOnInput('   ', 1_000))->toName())
            ->toThrow(InvalidPaymentItemName::class);
    });
});
