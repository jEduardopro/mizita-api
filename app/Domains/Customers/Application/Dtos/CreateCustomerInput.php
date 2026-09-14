<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

final readonly class CreateCustomerInput
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
            email: self::textOrNull($payload['email'] ?? null),
            phone: self::textOrNull($payload['phone'] ?? null),
        );
    }

    private static function textOrNull(?string $text): ?string
    {
        return $text ?: null;
    }
}
