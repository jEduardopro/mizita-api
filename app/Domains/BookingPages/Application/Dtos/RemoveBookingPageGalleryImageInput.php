<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\Dtos;

use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;

final readonly class RemoveBookingPageGalleryImageInput
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $imageId,
    ) {}

    /**
     * @throws BookingPageImageNotFound
     */
    public function validate(): void
    {
        $this->validateImageId();
    }

    private function validateImageId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->imageId) !== 1) {
            throw BookingPageImageNotFound::withId($this->imageId);
        }
    }
}
