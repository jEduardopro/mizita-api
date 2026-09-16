<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Contracts;

use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;

interface BookingPageImages
{
    public function bannerUrlFor(string $bookingPageId): ?string;

    /**
     * @return list<BookingPageImage>
     */
    public function galleryFor(string $bookingPageId): array;

    /**
     * @throws BookingPageNotFound
     */
    public function replaceBanner(string $bookingPageId, string $sourcePath, string $fileName): string;

    /**
     * @throws BookingPageNotFound
     */
    public function removeBanner(string $bookingPageId): void;

    /**
     * @throws BookingPageNotFound
     */
    public function addGalleryImage(string $bookingPageId, string $sourcePath, string $fileName): BookingPageImage;

    /**
     * @throws BookingPageNotFound
     * @throws BookingPageImageNotFound
     */
    public function removeGalleryImage(string $bookingPageId, string $imageId): void;

    /**
     * @param  list<string>  $imageIds
     *
     * @throws BookingPageNotFound
     * @throws BookingPageImageNotFound
     */
    public function reorderGallery(string $bookingPageId, array $imageIds): void;
}
