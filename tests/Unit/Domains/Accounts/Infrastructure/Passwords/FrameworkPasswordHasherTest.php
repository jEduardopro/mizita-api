<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Passwords\FrameworkPasswordHasher;
use Illuminate\Hashing\BcryptHasher;

it('returns a hash that verifies the plaintext and is not the plaintext', function () {
    $hasher = new BcryptHasher(['rounds' => 4]);

    $hash = (new FrameworkPasswordHasher($hasher))->hash('Tq7mW2xK9pLr4ZvB8nYd');

    expect($hash)->not->toBe('Tq7mW2xK9pLr4ZvB8nYd')
        ->and($hasher->check('Tq7mW2xK9pLr4ZvB8nYd', $hash))->toBeTrue();
});
