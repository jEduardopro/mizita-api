<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\ValueObjects\RegisteredPasskey;
use DateTimeImmutable;

final readonly class PasskeyData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $authenticator,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
    ) {}

    public static function fromRegisteredPasskey(RegisteredPasskey $passkey): self
    {
        return new self(
            id: $passkey->id,
            name: $passkey->name,
            authenticator: $passkey->authenticator,
            createdAt: $passkey->createdAt,
            lastUsedAt: $passkey->lastUsedAt,
        );
    }
}
