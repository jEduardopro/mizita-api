<?php

declare(strict_types=1);

namespace Tests\Support\BookingPages;

use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;

final class FakeBookingPageImages implements BookingPageImages
{
    private const URL_PREFIX = 'https://cdn.mizita.test/booking-pages/';

    private const FIRST_GALLERY_POSITION = 1;

    /**
     * @var array<string, string>
     */
    private array $banners = [];

    /**
     * @var array<string, list<BookingPageImage>>
     */
    private array $galleries = [];

    /**
     * @var list<array{bookingPageId: string, sourcePath: string, fileName: string}>
     */
    public array $bannersReplaced = [];

    /**
     * @var list<string>
     */
    public array $bannersRemoved = [];

    /**
     * @var list<array{bookingPageId: string, sourcePath: string, fileName: string}>
     */
    public array $galleryImagesAdded = [];

    /**
     * @var list<array{bookingPageId: string, imageId: string}>
     */
    public array $galleryImagesRemoved = [];

    /**
     * @var list<array{bookingPageId: string, imageIds: list<string>}>
     */
    public array $reorders = [];

    /**
     * @var list<string>
     */
    public array $galleryReads = [];

    public function withBanner(string $bookingPageId, string $url): self
    {
        $this->banners[$bookingPageId] = $url;

        return $this;
    }

    public function withGallery(string $bookingPageId, BookingPageImage ...$images): self
    {
        $this->galleries[$bookingPageId] = array_values($images);

        return $this;
    }

    public function withGalleryOf(string $bookingPageId, int $count): self
    {
        $images = [];

        for ($position = 1; $position <= $count; $position++) {
            $images[] = new BookingPageImage(
                id: sprintf('01930000-0000-7000-8000-%012d', $position),
                url: self::URL_PREFIX.$bookingPageId.'/'.$position.'.jpg',
                position: $position,
            );
        }

        return $this->withGallery($bookingPageId, ...$images);
    }

    public function bannerUrlFor(string $bookingPageId): ?string
    {
        return $this->banners[$bookingPageId] ?? null;
    }

    /**
     * @return list<BookingPageImage>
     */
    public function galleryFor(string $bookingPageId): array
    {
        $this->galleryReads[] = $bookingPageId;

        return $this->galleries[$bookingPageId] ?? [];
    }

    public function replaceBanner(string $bookingPageId, string $sourcePath, string $fileName): string
    {
        $this->bannersReplaced[] = [
            'bookingPageId' => $bookingPageId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        return $this->banners[$bookingPageId] = self::URL_PREFIX.$bookingPageId.'/'.$fileName;
    }

    public function removeBanner(string $bookingPageId): void
    {
        $this->bannersRemoved[] = $bookingPageId;

        unset($this->banners[$bookingPageId]);
    }

    public function addGalleryImage(string $bookingPageId, string $sourcePath, string $fileName): BookingPageImage
    {
        $this->galleryImagesAdded[] = [
            'bookingPageId' => $bookingPageId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        $gallery = $this->galleries[$bookingPageId] ?? [];

        $image = new BookingPageImage(
            id: sprintf('01930000-0000-7000-8000-%012d', count($gallery) + 1),
            url: self::URL_PREFIX.$bookingPageId.'/'.$fileName,
            position: count($gallery) + 1,
        );

        $this->galleries[$bookingPageId] = [...$gallery, $image];

        return $image;
    }

    public function removeGalleryImage(string $bookingPageId, string $imageId): void
    {
        $this->galleryImagesRemoved[] = ['bookingPageId' => $bookingPageId, 'imageId' => $imageId];

        $gallery = $this->galleries[$bookingPageId] ?? [];
        $remaining = array_values(array_filter(
            $gallery,
            static fn (BookingPageImage $image): bool => $image->id !== $imageId,
        ));

        if (count($remaining) === count($gallery)) {
            throw BookingPageImageNotFound::withId($imageId);
        }

        $this->galleries[$bookingPageId] = $remaining;
    }

    /**
     * @param  list<string>  $imageIds
     */
    public function reorderGallery(string $bookingPageId, array $imageIds): void
    {
        $this->reorders[] = ['bookingPageId' => $bookingPageId, 'imageIds' => array_values($imageIds)];

        $gallery = $this->galleries[$bookingPageId] ?? [];

        if (count($gallery) !== count($imageIds)) {
            throw InvalidGalleryOrder::incomplete();
        }

        $resolved = self::resolveAll($gallery, $imageIds);

        $reordered = [];

        foreach ($resolved as $offset => $image) {
            $reordered[] = new BookingPageImage(
                id: $image->id,
                url: $image->url,
                position: self::FIRST_GALLERY_POSITION + $offset,
            );
        }

        $this->galleries[$bookingPageId] = $reordered;
    }

    /**
     * @param  list<BookingPageImage>  $gallery
     * @param  list<string>  $imageIds
     * @return list<BookingPageImage>
     *
     * @throws BookingPageImageNotFound
     */
    private static function resolveAll(array $gallery, array $imageIds): array
    {
        $byId = [];

        foreach ($gallery as $image) {
            $byId[$image->id] = $image;
        }

        $resolved = [];

        foreach ($imageIds as $imageId) {
            $image = $byId[$imageId] ?? null;

            if (! $image instanceof BookingPageImage) {
                throw BookingPageImageNotFound::withId($imageId);
            }

            $resolved[] = $image;
        }

        return $resolved;
    }
}
