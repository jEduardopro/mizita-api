<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\CurrencyMismatch;
use App\Domains\Payments\Exceptions\InvalidMoneyAmount;
use App\Domains\Payments\ValueObjects\Money;
use App\Shared\ValueObjects\CurrencyCode;

function pesos(int $cents): Money
{
    return Money::fromCents($cents, CurrencyCode::default());
}

function dollars(int $cents): Money
{
    return Money::fromCents($cents, CurrencyCode::restore('USD'));
}

describe('building from cents', function () {
    it('keeps the amount and the currency it was given', function () {
        $money = Money::fromCents(1250, CurrencyCode::default());

        expect($money->amount)->toBe(1250)
            ->and($money->currency->value)->toBe('MXN');
    });

    it('holds an amount as an integer number of cents, never a float', function () {
        expect(pesos(1250)->amount)->toBeInt()
            ->and(Money::zero(CurrencyCode::default())->amount)->toBeInt()
            ->and(Money::fromDecimalString('0.29', CurrencyCode::default())->amount)->toBeInt();
    });

    it('hands out a zero of the currency asked for', function () {
        $zero = Money::zero(CurrencyCode::restore('USD'));

        expect($zero->amount)->toBe(0)
            ->and($zero->isZero())->toBeTrue()
            ->and($zero->currency->value)->toBe('USD');
    });

    it('refuses a negative amount', function () {
        expect(fn () => pesos(-1))
            ->toThrow(InvalidMoneyAmount::class, '[-1] is not a valid money amount.');
    });

    it('accepts the largest amount it serves', function () {
        expect(pesos(Money::MAXIMUM_CENTS)->amount)->toBe(9_999_999_999);
    });

    it('refuses an amount past the largest it serves', function () {
        expect(fn () => pesos(Money::MAXIMUM_CENTS + 1))
            ->toThrow(InvalidMoneyAmount::class, '[10000000000] exceeds the maximum money amount of 9999999999.');
    });
});

describe('parsing a decimal amount', function () {
    it('reads a written amount as whole cents', function (string $written, int $cents) {
        expect(Money::fromDecimalString($written, CurrencyCode::default())->amount)->toBe($cents);
    })->with([
        'two decimals' => ['100.00', 10000],
        'one decimal is tenths, not hundredths' => ['100.5', 10050],
        'no decimals at all' => ['7', 700],
        'zero' => ['0', 0],
        'zero with decimals' => ['0.00', 0],
        'a single cent' => ['0.01', 1],
        'a tenth' => ['0.10', 10],
        'leading zeroes' => ['000100.50', 10050],
        'surrounding whitespace' => ['  12.34  ', 1234],
        'the largest amount it serves' => ['99999999.99', 9_999_999_999],
    ]);

    it('reads an amount a float multiplication would round down by a cent', function (string $written, int $cents) {
        expect(Money::fromDecimalString($written, CurrencyCode::default())->amount)->toBe($cents);
    })->with([
        'the classic float trap' => ['0.29', 29],
        'eight twenty nine' => ['8.29', 829],
        'one fifteen' => ['1.15', 115],
        'thirty five fifteen' => ['35.15', 3515],
        'one point one' => ['1.10', 110],
    ]);

    it('refuses an amount that is not a plain amount with at most two decimals', function (string $written) {
        expect(fn () => Money::fromDecimalString($written, CurrencyCode::default()))
            ->toThrow(InvalidMoneyAmount::class);
    })->with([
        'three decimals' => '1.005',
        'empty' => '',
        'whitespace only' => '   ',
        'negative' => '-1',
        'negative with decimals' => '-10.50',
        'a plus sign' => '+1',
        'scientific notation' => '1e3',
        'no whole part' => '.50',
        'trailing separator' => '100.',
        'a comma separator' => '100,50',
        'a thousands separator' => '1,000.00',
        'two separators' => '1.0.0',
        'a currency sign' => '$100.00',
        'hexadecimal' => '0x10',
        'an inner space' => '10 0',
        'not a number at all' => 'free',
        'more digits than it serves' => '12345678901',
    ]);

    it('says the amount it could not read back to the caller', function () {
        expect(fn () => Money::fromDecimalString('1.005', CurrencyCode::default()))
            ->toThrow(InvalidMoneyAmount::class, '[1.005] is not a well formed decimal money amount.');
    });

    it('refuses a written amount above the largest it serves', function () {
        expect(fn () => Money::fromDecimalString('1000000000.00', CurrencyCode::default()))
            ->toThrow(InvalidMoneyAmount::class, 'exceeds the maximum money amount');
    });
});

describe('arithmetic', function () {
    it('adds two amounts of the same currency', function () {
        expect(pesos(1250)->plus(pesos(750))->amount)->toBe(2000);
    });

    it('subtracts two amounts of the same currency', function () {
        expect(pesos(1250)->minus(pesos(250))->amount)->toBe(1000);
    });

    it('keeps the currency of the amount being operated on', function () {
        expect(dollars(100)->plus(dollars(100))->currency->value)->toBe('USD')
            ->and(dollars(100)->minus(dollars(100))->currency->value)->toBe('USD');
    });

    it('refuses to go below zero', function () {
        expect(fn () => pesos(100)->minus(pesos(101)))
            ->toThrow(InvalidMoneyAmount::class, '[-1] is not a valid money amount.');
    });

    it('refuses to grow past the largest amount it serves', function () {
        expect(fn () => pesos(Money::MAXIMUM_CENTS)->plus(pesos(1)))
            ->toThrow(InvalidMoneyAmount::class, 'exceeds the maximum money amount');
    });

    it('compares two amounts of the same currency', function () {
        expect(pesos(1000)->isGreaterThan(pesos(999)))->toBeTrue()
            ->and(pesos(1000)->isGreaterThan(pesos(1000)))->toBeFalse()
            ->and(pesos(999)->isGreaterThan(pesos(1000)))->toBeFalse();
    });

    it('knows whether it holds nothing', function () {
        expect(pesos(0)->isZero())->toBeTrue()
            ->and(pesos(1)->isZero())->toBeFalse();
    });
});

describe('mixing currencies', function () {
    it('refuses to operate across two currencies', function () {
        $mismatch = 'Expected currency [MXN] but received [USD].';

        expect(fn () => pesos(100)->plus(dollars(100)))->toThrow(CurrencyMismatch::class, $mismatch)
            ->and(fn () => pesos(100)->minus(dollars(100)))->toThrow(CurrencyMismatch::class, $mismatch)
            ->and(fn () => pesos(100)->isGreaterThan(dollars(100)))->toThrow(CurrencyMismatch::class, $mismatch);
    });

    it('answers that two amounts in different currencies are unequal rather than refusing', function () {
        expect(pesos(100)->equals(dollars(100)))->toBeFalse();
    });

    it('holds two amounts equal only when the cents and the currency both match', function () {
        expect(pesos(100)->equals(pesos(100)))->toBeTrue()
            ->and(pesos(100)->equals(pesos(101)))->toBeFalse()
            ->and(dollars(0)->equals(Money::zero(CurrencyCode::restore('USD'))))->toBeTrue();
    });
});

it('computes money without ever reaching for a float', function (string $class) {
    $source = file_get_contents(dirname(__DIR__, 5)."/app/Domains/Payments/ValueObjects/{$class}.php");

    expect($source)->not->toMatch('/\(float\)|floatval|round\(|number_format\(|fdiv\(/');
})->with(['Money', 'Discount']);
