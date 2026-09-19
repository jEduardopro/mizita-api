<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicService
{
    /**
     * @param  list<string>  $staffIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public int $durationMinutes,
        public string $price,
        public ?string $imageUrl,
        public array $staffIds,
    ) {}

    /**
     * @param  list<string>  $staffIds
     */
    public function restrictedTo(array $staffIds): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            description: $this->description,
            durationMinutes: $this->durationMinutes,
            price: $this->price,
            imageUrl: $this->imageUrl,
            staffIds: array_values(array_intersect($this->staffIds, $staffIds)),
        );
    }
}
