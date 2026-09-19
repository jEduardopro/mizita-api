<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Domains\PublicCatalog\ValueObjects\PublicSlotQuery;

final readonly class ShowPublicAvailabilityInput
{
    public function __construct(
        public string $slug,
        public PublicSlotQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $slug): self
    {
        return new self(
            slug: $slug,
            query: new PublicSlotQuery(
                serviceId: self::textOrEmpty($payload['service_id'] ?? null),
                staffId: self::textOrEmpty($payload['staff_id'] ?? null),
                from: self::textOrEmpty($payload['from'] ?? null),
                to: self::textOrEmpty($payload['to'] ?? null),
            ),
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
