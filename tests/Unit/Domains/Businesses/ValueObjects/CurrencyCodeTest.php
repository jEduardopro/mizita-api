<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessCurrency;
use App\Domains\Businesses\ValueObjects\CurrencyCode;

describe('accepting a code', function () {
    it('accepts a three letter code and stores it upper case', function (string $code) {
        expect(CurrencyCode::fromString($code)->value)->toBe(strtoupper($code));
    })->with([
        'the default currency' => 'MXN',
        'the dollar' => 'USD',
        'the euro' => 'EUR',
    ]);

    it('trims what the caller padded', function () {
        expect(CurrencyCode::fromString('  MXN  ')->value)->toBe('MXN');
    });

    it('accepts a code nobody trades, because only the shape is checked here', function (string $code) {
        expect(CurrencyCode::fromString($code)->value)->toBe($code);
    })->with([
        'unassigned' => 'ZZZ',
        'a withdrawn currency' => 'DEM',
    ]);
});

describe('folding the case', function () {
    it('folds to upper case before validating, so one currency has one spelling', function (string $submitted) {
        expect(CurrencyCode::fromString($submitted)->value)->toBe('MXN');
    })->with([
        'lower case' => 'mxn',
        'mixed case' => 'MxN',
        'upper case' => 'MXN',
        'lower case and padded' => '  mxn  ',
    ]);

    it('treats every spelling of a code as the same currency', function () {
        expect(CurrencyCode::fromString('mxn')->equals(CurrencyCode::fromString('MXN')))->toBeTrue()
            ->and(CurrencyCode::fromString('MxN')->equals(CurrencyCode::default()))->toBeTrue();
    });
});

describe('refusing a code', function () {
    it('refuses anything that is not three letters', function (string $value) {
        expect(fn () => CurrencyCode::fromString($value))
            ->toThrow(InvalidBusinessCurrency::class, "[{$value}] is not a three letter ISO 4217 currency code.");
    })->with([
        'two letters' => 'MX',
        'four letters' => 'MXNN',
        'empty' => '',
        'whitespace' => '   ',
        'a digit inside' => 'M1X',
        'three digits' => '484',
        'a symbol' => '$$$',
        'an accent' => 'MXÑ',
        'a space inside' => 'M X',
    ]);

    it('names the value the caller sent, not the folded one, so the message matches what they typed', function () {
        expect(fn () => CurrencyCode::fromString('  mx  '))
            ->toThrow(InvalidBusinessCurrency::class, '[  mx  ] is not a three letter ISO 4217 currency code.');
    });
});

describe('the default', function () {
    it('is the Mexican peso', function () {
        expect(CurrencyCode::default()->value)->toBe('MXN');
    });

    it('is the same value a caller would get by spelling it out', function () {
        expect(CurrencyCode::default()->equals(CurrencyCode::fromString('MXN')))->toBeTrue();
    });
});

describe('rehydrating from storage', function () {
    it('accepts a stored value fromString would refuse', function (string $stored) {
        expect(CurrencyCode::restore($stored)->value)->toBe($stored);
    })->with([
        'lower case written before folding existed' => 'mxn',
        'a retired shape' => 'PESO',
        'empty' => '',
    ]);
});

describe('equality', function () {
    it('compares by value', function () {
        expect(CurrencyCode::fromString('MXN')->equals(CurrencyCode::fromString('USD')))->toBeFalse();
    });

    it('tells a stored lower case row apart from the folded value, which is why reads restore verbatim', function () {
        expect(CurrencyCode::restore('mxn')->equals(CurrencyCode::default()))->toBeFalse();
    });
});
