<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;

final readonly class ReschedulePublicBookingInput
{
    public function __construct(
        public string $slug,
        public PublicBookingCredentials $credentials,
        public string $startsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(
        array $payload,
        string $slug,
        PublicBookingCredentials $credentials,
    ): self {
        return new self(
            slug: $slug,
            credentials: $credentials,
            startsAt: self::textOrEmpty($payload['starts_at'] ?? null),
        );
    }

    /**
     * @throws BusinessPageNotFound
     */
    public function validate(): void
    {
        $this->validateSlug();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateSlug(): void
    {
        BusinessPageSlug::fromString($this->slug);
    }
}
