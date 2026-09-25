<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

use DateTimeImmutable;

final readonly class RegisteredPasskey
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $authenticator,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
    ) {}
}
