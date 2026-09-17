<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Contracts;

use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;

interface BookingPageImages
{
    public function bannerUrlFor(string $businessId, string $bookingPageId): ?string;

    /**
     * @return list<BookingPageImage>
     */
    public function galleryFor(string $businessId, string $bookingPageId): array;

    /**
     * @throws BookingPageNotFound
     */
    public function replaceBanner(string $businessId, string $bookingPageId, string $sourcePath, string $fileName): string;

    /**
     * @throws BookingPageNotFound
     */
    public function removeBanner(string $businessId, string $bookingPageId): void;

    /**
     * @throws BookingPageNotFound
     */
    public function addGalleryImage(string $businessId, string $bookingPageId, string $sourcePath, string $fileName): BookingPageImage;

    /**
     * @throws BookingPageNotFound
     * @throws BookingPageImageNotFound
     */
    public function removeGalleryImage(string $businessId, string $bookingPageId, string $imageId): void;

    /**
     * @param  list<string>  $imageIds
     *
     * @throws BookingPageNotFound
     * @throws BookingPageImageNotFound
     * @throws InvalidGalleryOrder
     */
    public function reorderGallery(string $businessId, string $bookingPageId, array $imageIds): void;
}
