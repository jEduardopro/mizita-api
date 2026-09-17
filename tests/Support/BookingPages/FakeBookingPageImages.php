<?php

declare(strict_types=1);

namespace Tests\Support\BookingPages;

use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;

final class FakeBookingPageImages implements BookingPageImages
{
    private const URL_PREFIX = 'https://cdn.mizita.test/booking-pages/';

    private const FIRST_GALLERY_POSITION = 1;

    /**
     * @var array<string, array<string, true>>
     */
    private array $pages = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $banners = [];

    /**
     * @var array<string, array<string, list<BookingPageImage>>>
     */
    private array $galleries = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string}>
     */
    public array $bannerReads = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string}>
     */
    public array $galleryReads = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string, sourcePath: string, fileName: string}>
     */
    public array $bannersReplaced = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string}>
     */
    public array $bannersRemoved = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string, sourcePath: string, fileName: string}>
     */
    public array $galleryImagesAdded = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string, imageId: string}>
     */
    public array $galleryImagesRemoved = [];

    /**
     * @var list<array{businessId: string, bookingPageId: string, imageIds: list<string>}>
     */
    public array $reorders = [];

    public function withPage(BookingPage ...$pages): self
    {
        foreach ($pages as $page) {
            $this->pages[$page->businessId][$page->id] = true;
        }

        return $this;
    }

    public function withBanner(BookingPage $page, string $url): self
    {
        $this->withPage($page);

        $this->banners[$page->businessId][$page->id] = $url;

        return $this;
    }

    public function withGallery(BookingPage $page, BookingPageImage ...$images): self
    {
        $this->withPage($page);

        $this->galleries[$page->businessId][$page->id] = array_values($images);

        return $this;
    }

    public function withGalleryOf(BookingPage $page, int $count): self
    {
        $images = [];

        for ($position = self::FIRST_GALLERY_POSITION; $position <= $count; $position++) {
            $images[] = new BookingPageImage(
                id: sprintf('01930000-0000-7000-8000-%012d', $position),
                url: self::URL_PREFIX.$page->id.'/'.$position.'.jpg',
                position: $position,
            );
        }

        return $this->withGallery($page, ...$images);
    }

    public function bannerUrlFor(string $businessId, string $bookingPageId): ?string
    {
        $this->bannerReads[] = ['businessId' => $businessId, 'bookingPageId' => $bookingPageId];

        return $this->banners[$businessId][$bookingPageId] ?? null;
    }

    /**
     * @return list<BookingPageImage>
     */
    public function galleryFor(string $businessId, string $bookingPageId): array
    {
        $this->galleryReads[] = ['businessId' => $businessId, 'bookingPageId' => $bookingPageId];

        return $this->galleries[$businessId][$bookingPageId] ?? [];
    }

    public function replaceBanner(string $businessId, string $bookingPageId, string $sourcePath, string $fileName): string
    {
        $this->refusePageOutsideBusiness($businessId, $bookingPageId);

        $this->bannersReplaced[] = [
            'businessId' => $businessId,
            'bookingPageId' => $bookingPageId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        return $this->banners[$businessId][$bookingPageId] = self::URL_PREFIX.$bookingPageId.'/'.$fileName;
    }

    public function removeBanner(string $businessId, string $bookingPageId): void
    {
        $this->refusePageOutsideBusiness($businessId, $bookingPageId);

        $this->bannersRemoved[] = ['businessId' => $businessId, 'bookingPageId' => $bookingPageId];

        unset($this->banners[$businessId][$bookingPageId]);
    }

    public function addGalleryImage(string $businessId, string $bookingPageId, string $sourcePath, string $fileName): BookingPageImage
    {
        $this->refusePageOutsideBusiness($businessId, $bookingPageId);

        $this->galleryImagesAdded[] = [
            'businessId' => $businessId,
            'bookingPageId' => $bookingPageId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        $gallery = $this->galleries[$businessId][$bookingPageId] ?? [];

        $image = new BookingPageImage(
            id: sprintf('01930000-0000-7000-8000-%012d', count($gallery) + 1),
            url: self::URL_PREFIX.$bookingPageId.'/'.$fileName,
            position: count($gallery) + 1,
        );

        $this->galleries[$businessId][$bookingPageId] = [...$gallery, $image];

        return $image;
    }

    public function removeGalleryImage(string $businessId, string $bookingPageId, string $imageId): void
    {
        $this->refusePageOutsideBusiness($businessId, $bookingPageId);

        $this->galleryImagesRemoved[] = [
            'businessId' => $businessId,
            'bookingPageId' => $bookingPageId,
            'imageId' => $imageId,
        ];

        $gallery = $this->galleries[$businessId][$bookingPageId] ?? [];
        $remaining = array_values(array_filter(
            $gallery,
            static fn (BookingPageImage $image): bool => $image->id !== $imageId,
        ));

        if (count($remaining) === count($gallery)) {
            throw BookingPageImageNotFound::withId($imageId);
        }

        $this->galleries[$businessId][$bookingPageId] = $remaining;
    }

    /**
     * @param  list<string>  $imageIds
     */
    public function reorderGallery(string $businessId, string $bookingPageId, array $imageIds): void
    {
        $this->refusePageOutsideBusiness($businessId, $bookingPageId);

        $this->reorders[] = [
            'businessId' => $businessId,
            'bookingPageId' => $bookingPageId,
            'imageIds' => array_values($imageIds),
        ];

        $gallery = $this->galleries[$businessId][$bookingPageId] ?? [];

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

        $this->galleries[$businessId][$bookingPageId] = $reordered;
    }

    /**
     * @throws BookingPageNotFound
     */
    private function refusePageOutsideBusiness(string $businessId, string $bookingPageId): void
    {
        if (! isset($this->pages[$businessId][$bookingPageId])) {
            throw BookingPageNotFound::forBusiness($businessId);
        }
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
