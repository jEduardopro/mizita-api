<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Media;

use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
use App\Shared\Contracts\TransactionManager;
use App\Shared\Infrastructure\Media\SafeFileName;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SpatieBookingPageImages implements BookingPageImages
{
    private const FIRST_GALLERY_POSITION = 1;

    private const FALLBACK_FILE_NAME = 'image';

    public function __construct(
        private readonly TransactionManager $transactions,
    ) {}

    public function bannerUrlFor(string $bookingPageId): ?string
    {
        $model = BookingPageModel::query()->with('media')->where('uuid', $bookingPageId)->first();

        if ($model === null) {
            return null;
        }

        $url = $model->getFirstMediaUrl(BookingPageModel::BANNER_COLLECTION);

        return $url === '' ? null : $url;
    }

    /**
     * @return list<BookingPageImage>
     */
    public function galleryFor(string $bookingPageId): array
    {
        $model = BookingPageModel::query()->with('media')->where('uuid', $bookingPageId)->first();

        if ($model === null) {
            return [];
        }

        return $model->getMedia(BookingPageModel::GALLERY_COLLECTION)
            ->map(static fn (Media $image): BookingPageImage => new BookingPageImage(
                id: (string) $image->uuid,
                url: $image->getUrl(),
                position: (int) $image->order_column,
            ))
            ->values()
            ->all();
    }

    public function replaceBanner(string $bookingPageId, string $sourcePath, string $fileName): string
    {
        return $this->modelOrFail($bookingPageId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(BookingPageModel::BANNER_COLLECTION)
            ->getUrl();
    }

    public function removeBanner(string $bookingPageId): void
    {
        $this->modelOrFail($bookingPageId)->clearMediaCollection(BookingPageModel::BANNER_COLLECTION);
    }

    public function addGalleryImage(string $bookingPageId, string $sourcePath, string $fileName): BookingPageImage
    {
        $image = $this->modelOrFail($bookingPageId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(BookingPageModel::GALLERY_COLLECTION);

        return new BookingPageImage(
            id: (string) $image->uuid,
            url: $image->getUrl(),
            position: (int) $image->order_column,
        );
    }

    public function removeGalleryImage(string $bookingPageId, string $imageId): void
    {
        $this->galleryImageOrFail($bookingPageId, $imageId)->delete();
    }

    /**
     * @param  list<string>  $imageIds
     */
    public function reorderGallery(string $bookingPageId, array $imageIds): void
    {
        $gallery = $this->modelOrFail($bookingPageId)->getMedia(BookingPageModel::GALLERY_COLLECTION);

        if ($gallery->count() !== count($imageIds)) {
            throw InvalidGalleryOrder::incomplete();
        }

        $ordered = self::resolveAll($gallery, $imageIds);

        $this->transactions->run(static function () use ($ordered): void {
            foreach ($ordered as $offset => $image) {
                $image->order_column = self::FIRST_GALLERY_POSITION + $offset;
                $image->save();
            }
        });
    }

    /**
     * @param  MediaCollection<int, Media>  $gallery
     * @param  list<string>  $imageIds
     * @return list<Media>
     *
     * @throws BookingPageImageNotFound
     */
    private static function resolveAll(MediaCollection $gallery, array $imageIds): array
    {
        $resolved = [];

        foreach ($imageIds as $imageId) {
            $image = $gallery->firstWhere('uuid', $imageId);

            if (! $image instanceof Media) {
                throw BookingPageImageNotFound::withId($imageId);
            }

            $resolved[] = $image;
        }

        return $resolved;
    }

    private function modelOrFail(string $bookingPageId): BookingPageModel
    {
        $model = BookingPageModel::query()->where('uuid', $bookingPageId)->first();

        if ($model === null) {
            throw BookingPageNotFound::forBusiness($bookingPageId);
        }

        return $model;
    }

    private function galleryImageOrFail(string $bookingPageId, string $imageId): Media
    {
        $image = $this->modelOrFail($bookingPageId)
            ->getMedia(BookingPageModel::GALLERY_COLLECTION)
            ->firstWhere('uuid', $imageId);

        if (! $image instanceof Media) {
            throw BookingPageImageNotFound::withId($imageId);
        }

        return $image;
    }
}
