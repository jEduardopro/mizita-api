<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressPostalCode;
use App\Domains\Addresses\ValueObjects\PostalCode;

it('accepts a run of digits inside the bounds', function (string $value) {
    expect(PostalCode::fromString($value)->value)->toBe($value);
})->with([
    'the shortest allowed' => str_repeat('1', PostalCode::MINIMUM_DIGITS),
    'a Mexican code' => '03940',
    'a five digit code starting at zero' => '01000',
    'the longest allowed' => str_repeat('1', PostalCode::MAXIMUM_DIGITS),
]);

it('keeps the leading zero, because a postal code is text and not a number', function () {
    expect(PostalCode::fromString('03940')->value)->toBeString()->toBe('03940');
});

it('trims the padding a form leaves around it', function () {
    expect(PostalCode::fromString("  03940 \t ")->value)->toBe('03940');
});

it('refuses a postal code that carries nothing', function (string $value) {
    expect(fn () => PostalCode::fromString($value))
        ->toThrow(InvalidAddressPostalCode::class, 'An address postal code cannot be empty.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
    'newline' => "\n",
]);

it('refuses one digit short of the minimum', function () {
    expect(fn () => PostalCode::fromString(str_repeat('1', PostalCode::MINIMUM_DIGITS - 1)))
        ->toThrow(InvalidAddressPostalCode::class, 'The postal code offered is not a run of digits an address may carry.');
});

it('refuses one digit past the maximum', function () {
    expect(fn () => PostalCode::fromString(str_repeat('1', PostalCode::MAXIMUM_DIGITS + 1)))
        ->toThrow(InvalidAddressPostalCode::class);
});

it('refuses anything that is not a plain run of ascii digits', function (string $value) {
    expect(fn () => PostalCode::fromString($value))->toThrow(InvalidAddressPostalCode::class);
})->with([
    'letters' => 'SW1A1AA',
    'a letter among the digits' => '0394O',
    'a dash' => '03940-1234',
    'a space inside' => '039 40',
    'a plus' => '+03940',
    'a decimal point' => '039.40',
    'eastern arabic digits' => '٠٣٩٤٠',
    'a newline in the middle' => "039\n40",
]);

it('reads a trailing newline as padding rather than as a character it must refuse', function () {
    expect(PostalCode::fromString("03940\n")->value)->toBe('03940');
});

it('restores a stored code without asking the rules again', function () {
    expect(PostalCode::restore('not-a-code')->value)->toBe('not-a-code');
});

it('is equal to another code holding the same digits', function () {
    expect(PostalCode::fromString('03940')->equals(PostalCode::restore('03940')))->toBeTrue();
});

it('is not equal to a code with different digits, padding included', function (string $other) {
    expect(PostalCode::fromString('03940')->equals(PostalCode::restore($other)))->toBeFalse();
})->with([
    'another code' => '44100',
    'the same digits padded' => ' 03940',
    'the same digits without the leading zero' => '3940',
]);
