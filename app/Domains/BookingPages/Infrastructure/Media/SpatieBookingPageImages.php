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
use App\Shared\Infrastructure\Media\OptimizedImageUrl;
use App\Shared\Infrastructure\Media\SafeFileName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SpatieBookingPageImages implements BookingPageImages
{
    private const FIRST_GALLERY_POSITION = 1;

    private const FALLBACK_FILE_NAME = 'image';

    private const BUSINESSES_TABLE = 'businesses';

    public function __construct(
        private readonly TransactionManager $transactions,
    ) {}

    public function bannerUrlFor(string $businessId, string $bookingPageId): ?string
    {
        $banner = self::bannerOf($businessId, $bookingPageId);

        return $banner === null ? null : OptimizedImageUrl::of($banner);
    }

    public function originalBannerUrlFor(string $businessId, string $bookingPageId): ?string
    {
        return self::bannerOf($businessId, $bookingPageId)?->getUrl();
    }

    /**
     * @return list<BookingPageImage>
     */
    public function galleryFor(string $businessId, string $bookingPageId): array
    {
        $model = self::ofBusiness($businessId)->with('media')->where('uuid', $bookingPageId)->first();

        if ($model === null) {
            return [];
        }

        return $model->getMedia(BookingPageModel::GALLERY_COLLECTION)
            ->map(static fn (Media $image): BookingPageImage => self::galleryImageOf($image))
            ->values()
            ->all();
    }

    public function replaceBanner(string $businessId, string $bookingPageId, string $sourcePath, string $fileName): string
    {
        $banner = $this->modelOrFail($businessId, $bookingPageId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($sourcePath, $fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(BookingPageModel::BANNER_COLLECTION);

        return OptimizedImageUrl::of($banner);
    }

    public function removeBanner(string $businessId, string $bookingPageId): void
    {
        $this->modelOrFail($businessId, $bookingPageId)->clearMediaCollection(BookingPageModel::BANNER_COLLECTION);
    }

    public function addGalleryImage(string $businessId, string $bookingPageId, string $sourcePath, string $fileName): BookingPageImage
    {
        $image = $this->modelOrFail($businessId, $bookingPageId)
            ->addMedia($sourcePath)
            ->usingFileName(SafeFileName::from($sourcePath, $fileName, self::FALLBACK_FILE_NAME))
            ->toMediaCollection(BookingPageModel::GALLERY_COLLECTION);

        return self::galleryImageOf($image);
    }

    public function removeGalleryImage(string $businessId, string $bookingPageId, string $imageId): void
    {
        $this->galleryImageOrFail($businessId, $bookingPageId, $imageId)->delete();
    }

    /**
     * @param  list<string>  $imageIds
     */
    public function reorderGallery(string $businessId, string $bookingPageId, array $imageIds): void
    {
        $gallery = $this->modelOrFail($businessId, $bookingPageId)->getMedia(BookingPageModel::GALLERY_COLLECTION);

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

    private static function bannerOf(string $businessId, string $bookingPageId): ?Media
    {
        return self::ofBusiness($businessId)
            ->with('media')
            ->where('uuid', $bookingPageId)
            ->first()
            ?->getFirstMedia(BookingPageModel::BANNER_COLLECTION);
    }

    private static function galleryImageOf(Media $image): BookingPageImage
    {
        return new BookingPageImage(
            id: (string) $image->uuid,
            url: OptimizedImageUrl::of($image),
            position: (int) $image->order_column,
        );
    }

    private function modelOrFail(string $businessId, string $bookingPageId): BookingPageModel
    {
        $model = self::ofBusiness($businessId)->where('uuid', $bookingPageId)->first();

        if ($model === null) {
            throw BookingPageNotFound::forBusiness($businessId);
        }

        return $model;
    }

    private function galleryImageOrFail(string $businessId, string $bookingPageId, string $imageId): Media
    {
        $image = $this->modelOrFail($businessId, $bookingPageId)
            ->getMedia(BookingPageModel::GALLERY_COLLECTION)
            ->firstWhere('uuid', $imageId);

        if (! $image instanceof Media) {
            throw BookingPageImageNotFound::withId($imageId);
        }

        return $image;
    }

    /**
     * @return Builder<BookingPageModel>
     */
    private static function ofBusiness(string $businessId): Builder
    {
        return BookingPageModel::query()->whereIn(
            'business_id',
            static fn (QueryBuilder $query) => $query
                ->select('id')
                ->from(self::BUSINESSES_TABLE)
                ->where('uuid', $businessId),
        );
    }
}
