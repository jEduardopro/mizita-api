<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

final readonly class CheckBusinessNameAvailabilityInput
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            name: (string) $payload['name'],
        );
    }
}
