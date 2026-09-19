<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidReferenceCode;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use App\Shared\ValueObjects\DomainFailureKind;

it('declares an alphabet of thirty two symbols with no ambiguous ones', function () {
    expect(ReferenceCode::ALPHABET)->toBe('23456789ABCDEFGHJKLMNPQRSTUVWXYZ')
        ->and(strlen(ReferenceCode::ALPHABET))->toBe(32)
        ->and(count(array_unique(str_split(ReferenceCode::ALPHABET))))->toBe(32)
        ->and(ReferenceCode::LENGTH)->toBe(8);
});

it('accepts a code built from the alphabet', function (string $value) {
    expect(ReferenceCode::fromString($value)->value)->toBe($value);
})->with([
    'digits only' => '23456789',
    'letters only' => 'ABCDEFGH',
    'mixed' => 'A2B3C4D5',
    'the last symbols' => 'STUVWXYZ',
]);

it('raises a code the customer typed in lower case', function () {
    expect(ReferenceCode::fromString('a2b3c4d5')->value)->toBe('A2B3C4D5');
});

it('accepts a code the customer pasted with surrounding spaces', function () {
    expect(ReferenceCode::fromString('  A2B3C4D5  ')->value)->toBe('A2B3C4D5');
});

it('refuses a code carrying a symbol the alphabet leaves out on purpose', function (string $value) {
    expect(fn () => ReferenceCode::fromString($value))->toThrow(InvalidReferenceCode::class);
})->with([
    'the letter I' => 'A2B3C4DI',
    'the letter O' => 'A2B3C4DO',
    'the digit zero' => 'A2B3C4D0',
    'the digit one' => 'A2B3C4D1',
    'the letter I lowercased' => 'a2b3c4di',
    'the digit zero in the middle' => 'A20BC4D5',
]);

it('refuses a code that is not eight symbols long', function (string $value) {
    expect(fn () => ReferenceCode::fromString($value))->toThrow(InvalidReferenceCode::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'seven symbols' => 'A2B3C4D',
    'nine symbols' => 'A2B3C4D5E',
]);

it('refuses a code carrying punctuation, a space inside or an accent', function (string $value) {
    expect(fn () => ReferenceCode::fromString($value))->toThrow(InvalidReferenceCode::class);
})->with([
    'a hyphen' => 'A2B3-4D5',
    'a space inside' => 'A2B3 4D5',
    'an accent' => 'A2B3C4DÑ',
]);

it('refuses as a domain failure the responder can classify', function () {
    try {
        ReferenceCode::fromString('A2B3C4D0');
        $thrown = null;
    } catch (InvalidReferenceCode $refusal) {
        $thrown = $refusal;
    }

    expect($thrown?->errorCode())->toBe('invalid_reference_code')
        ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
});

it('restores whatever the column holds, skipping the rules that guard a new code', function () {
    expect(ReferenceCode::restore('lowercase-0')->value)->toBe('lowercase-0');
});

it('holds two equal codes to be the same', function () {
    expect(ReferenceCode::fromString('A2B3C4D5')->equals(ReferenceCode::fromString('a2b3c4d5')))->toBeTrue()
        ->and(ReferenceCode::fromString('A2B3C4D5')->equals(ReferenceCode::fromString('A2B3C4D6')))->toBeFalse();
});
