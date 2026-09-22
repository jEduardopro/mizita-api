<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\InvalidPaymentItemName;
use App\Domains\Payments\ValueObjects\PaymentItemName;

it('keeps the name it was given', function () {
    expect(PaymentItemName::fromString('Corte de cabello')->value)->toBe('Corte de cabello');
});

it('trims the whitespace around a name', function (string $written) {
    expect(PaymentItemName::fromString($written)->value)->toBe('Corte');
})->with([
    'leading spaces' => '   Corte',
    'trailing spaces' => 'Corte   ',
    'both sides' => '  Corte  ',
    'a tab' => "\tCorte\t",
    'a newline' => "\nCorte\n",
]);

it('keeps accents, unicode and inner spacing untouched', function (string $written) {
    expect(PaymentItemName::fromString($written)->value)->toBe($written);
})->with([
    'accents' => 'Depilación láser',
    'an enye' => 'Diseño de uñas',
    'an emoji' => 'Corte ✂️',
    'inner double space' => 'Corte  premium',
    'cyrillic' => 'Стрижка',
]);

it('refuses a name with nothing in it', function (string $written) {
    expect(fn () => PaymentItemName::fromString($written))
        ->toThrow(InvalidPaymentItemName::class, 'A payment item name cannot be empty.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'a tab' => "\t",
    'a newline' => "\n",
]);

it('accepts a name of exactly the longest length it serves', function () {
    expect(PaymentItemName::fromString(str_repeat('a', PaymentItemName::MAXIMUM_LENGTH))->value)
        ->toHaveLength(120);
});

it('measures the length in characters, not bytes', function () {
    expect(PaymentItemName::fromString(str_repeat('á', PaymentItemName::MAXIMUM_LENGTH))->value)
        ->toBe(str_repeat('á', 120));
});

it('refuses a name past the longest length it serves', function (string $written) {
    expect(fn () => PaymentItemName::fromString($written))
        ->toThrow(InvalidPaymentItemName::class, 'A payment item name cannot exceed 120 characters.');
})->with([
    'one character too long' => str_repeat('a', PaymentItemName::MAXIMUM_LENGTH + 1),
    'one accented character too long' => str_repeat('á', PaymentItemName::MAXIMUM_LENGTH + 1),
    'far too long' => str_repeat('a', 500),
]);

it('counts the trimmed name against the maximum, not the padding', function () {
    $padded = '  '.str_repeat('a', PaymentItemName::MAXIMUM_LENGTH).'  ';

    expect(PaymentItemName::fromString($padded)->value)->toHaveLength(120);
});

it('restores a stored name verbatim, skipping the rules it would refuse', function () {
    expect(PaymentItemName::restore('')->value)->toBe('')
        ->and(PaymentItemName::restore('  padded  ')->value)->toBe('  padded  ')
        ->and(PaymentItemName::restore(str_repeat('a', 500))->value)->toHaveLength(500);
});
