<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

final readonly class AccountSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public bool $hasPassword,
        public bool $awaitingPasswordChange = false,
    ) {}
}
