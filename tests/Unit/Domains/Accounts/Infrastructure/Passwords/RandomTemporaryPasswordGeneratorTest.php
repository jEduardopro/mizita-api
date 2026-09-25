<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Passwords\RandomTemporaryPasswordGenerator;
use App\Domains\Accounts\ValueObjects\TemporaryPassword;

beforeEach(function () {
    $this->generator = new RandomTemporaryPasswordGenerator;
});

it('generates a password comfortably above the minimum length', function () {
    expect(mb_strlen($this->generator->generate()->value))->toBe(20)
        ->toBeGreaterThanOrEqual(TemporaryPassword::MINIMUM_LENGTH);
});

it('uses letters and digits only, so the password survives being read aloud or pasted', function () {
    expect($this->generator->generate()->value)->toMatch('/^[A-Za-z0-9]{20}$/');
});

it('never hands out the same password twice', function () {
    $passwords = array_map(fn (): string => $this->generator->generate()->value, range(1, 50));

    expect(array_unique($passwords))->toHaveCount(50);
});
