<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\TemporaryPasswordTooShort;
use App\Domains\Accounts\ValueObjects\TemporaryPassword;
use App\Shared\Contracts\DomainFailure;

it('keeps the plaintext exactly as it was given', function () {
    expect(TemporaryPassword::fromString('Tq7mW2xK9pLr4ZvB8nYd')->value)->toBe('Tq7mW2xK9pLr4ZvB8nYd');
});

it('accepts a password at exactly the minimum length', function () {
    $plaintext = str_repeat('a', TemporaryPassword::MINIMUM_LENGTH);

    expect(TemporaryPassword::fromString($plaintext)->value)->toBe($plaintext);
});

it('rejects a password shorter than the minimum, naming the minimum', function (string $plaintext) {
    expect(fn () => TemporaryPassword::fromString($plaintext))
        ->toThrow(TemporaryPasswordTooShort::class, 'A temporary password takes at least [16] characters.');
})->with([
    'empty' => '',
    'one short of the minimum' => str_repeat('a', TemporaryPassword::MINIMUM_LENGTH - 1),
    'fifteen multibyte characters, thirty bytes' => str_repeat('ñ', TemporaryPassword::MINIMUM_LENGTH - 1),
]);

it('counts characters rather than bytes', function () {
    $plaintext = str_repeat('ñ', TemporaryPassword::MINIMUM_LENGTH);

    expect(TemporaryPassword::fromString($plaintext)->value)->toBe($plaintext);
});

it('treats a short password as a programmer error, never as a refusal the caller could trigger', function () {
    expect(TemporaryPasswordTooShort::belowMinimum(TemporaryPassword::MINIMUM_LENGTH))
        ->toBeInstanceOf(LogicException::class)
        ->not->toBeInstanceOf(DomainFailure::class);
});
