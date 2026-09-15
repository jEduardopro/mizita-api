<?php

declare(strict_types=1);

use App\Domains\Services\Exceptions\InvalidServicePrice;
use App\Domains\Services\ValueObjects\Price;

it('keeps an amount that already carries two decimals', function () {
    expect(Price::fromString('250.00')->amount)->toBe('250.00');
});

it('normalises an amount to two decimals without ever touching a float', function (string $value, string $amount) {
    expect(Price::fromString($value)->amount)->toBe($amount);
})->with([
    'no decimals' => ['100', '100.00'],
    'one decimal' => ['100.5', '100.50'],
    'a tenth that a float would round' => ['0.10', '0.10'],
    'a cent' => ['0.01', '0.01'],
    'zero' => ['0', '0.00'],
    'padded zero' => ['0.0', '0.00'],
    'leading zeroes' => ['000100', '100.00'],
    'only zeroes' => ['0000', '0.00'],
    'surrounding whitespace' => ['  250.5  ', '250.50'],
    'the largest amount it serves' => ['99999999.99', '99999999.99'],
]);

it('rejects a negative amount for being negative, not for its shape', function (string $value) {
    expect(fn () => Price::fromString($value))
        ->toThrow(InvalidServicePrice::class, 'A service price cannot be negative.');
})->with([
    'minus one' => '-1',
    'minus with decimals' => '-10.50',
    'minus zero' => '-0.00',
    'padded minus' => '  -1  ',
]);

it('rejects an amount that is not a plain decimal', function (string $value) {
    expect(fn () => Price::fromString($value))
        ->toThrow(InvalidServicePrice::class, 'A service price is an amount with at most two decimals.');
})->with([
    'scientific notation' => '1e3',
    'three decimals' => '100.555',
    'empty' => '',
    'whitespace only' => '   ',
    'a comma separator' => '100,50',
    'thousands separator' => '1,000.00',
    'no whole part' => '.50',
    'trailing separator' => '100.',
    'two separators' => '1.0.0',
    'more digits than it serves' => '999999999',
    'a currency sign' => '$100.00',
    'a plus sign' => '+100.00',
    'not a number at all' => 'free',
    'hexadecimal' => '0x10',
    'an inner space' => '10 0',
]);

it('hands out a free price at two decimals', function () {
    expect(Price::free()->amount)->toBe('0.00')
        ->and(Price::free()->isFree())->toBeTrue();
});

it('knows a parsed zero is free', function (string $value) {
    expect(Price::fromString($value)->isFree())->toBeTrue();
})->with(['zero' => '0', 'zero with decimals' => '0.00', 'padded zero' => '00.0']);

it('knows a price with an amount on it is not free', function (string $value) {
    expect(Price::fromString($value)->isFree())->toBeFalse();
})->with(['a cent' => '0.01', 'a tenth' => '0.10', 'a round amount' => '250']);

it('restores a stored amount verbatim, skipping the shape it would refuse', function () {
    expect(Price::restore('250.00')->amount)->toBe('250.00')
        ->and(Price::restore('whatever')->amount)->toBe('whatever');
});

it('compares two prices by their normalised amount', function () {
    expect(Price::fromString('100')->equals(Price::fromString('100.00')))->toBeTrue()
        ->and(Price::fromString('100')->equals(Price::fromString('100.01')))->toBeFalse()
        ->and(Price::restore('0')->equals(Price::free()))->toBeFalse();
});
