<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\Dtos;

use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;

final readonly class ReorderBookingPageGalleryInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    /**
     * @param  list<string>  $imageIds
     */
    public function __construct(
        public array $imageIds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        $imageIds = $payload['images'] ?? [];

        return new self(array_values(array_map(
            static fn (mixed $imageId): string => is_string($imageId) ? $imageId : '',
            is_array($imageIds) ? $imageIds : [],
        )));
    }

    /**
     * @throws InvalidGalleryOrder
     * @throws BookingPageImageNotFound
     */
    public function validate(): void
    {
        $this->validateImageIds();
    }

    private function validateImageIds(): void
    {
        if ($this->imageIds === []) {
            throw InvalidGalleryOrder::incomplete();
        }

        if (count(array_unique($this->imageIds)) !== count($this->imageIds)) {
            throw InvalidGalleryOrder::incomplete();
        }

        foreach ($this->imageIds as $imageId) {
            if (preg_match(self::UUID_PATTERN, $imageId) !== 1) {
                throw BookingPageImageNotFound::withId($imageId);
            }
        }
    }
}
