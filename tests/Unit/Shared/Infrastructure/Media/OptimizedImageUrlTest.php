<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Eloquent\Models\MediaModel;
use App\Shared\Infrastructure\Media\OptimizedImageUrl;
use Illuminate\Database\Eloquent\Collection;
use Tests\Support\Media\OptimizedImageOwnerModel;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->versionStamp = '?v='.(new DateTimeImmutable('2026-01-01 12:00:00 UTC'))->getTimestamp();

    $this->storedImage = function (
        int $key = 42,
        string $fileName = 'banner.jpg',
        array $generatedConversions = ['optimized' => true],
        string $collection = OptimizedImageOwnerModel::PHOTO_COLLECTION,
        int $position = 1,
    ): MediaModel {
        $media = (new MediaModel)->forceFill([
            'id' => $key,
            'model_type' => OptimizedImageOwnerModel::class,
            'model_id' => 1,
            'collection_name' => $collection,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => $generatedConversions,
            'responsive_images' => [],
            'order_column' => $position,
            'updated_at' => '2026-01-01 12:00:00',
        ]);
        $media->exists = true;

        return $media;
    };

    $this->ownerHolding = function (MediaModel ...$media): OptimizedImageOwnerModel {
        $owner = new OptimizedImageOwnerModel;
        $owner->exists = true;
        $owner->setRelation('media', new Collection($media));

        return $owner;
    };
});

describe('the url of one stored image', function () {
    it('points at the compressed webp copy once it was generated', function () {
        expect(OptimizedImageUrl::of(($this->storedImage)()))
            ->toEndWith('/42/conversions/banner-optimized.webp'.$this->versionStamp);
    });

    it('falls back to the uploaded file while no compressed copy exists', function () {
        expect(OptimizedImageUrl::of(($this->storedImage)(generatedConversions: [])))
            ->toEndWith('/42/banner.jpg'.$this->versionStamp);
    });

    it('falls back to the uploaded file when the compressed copy failed to generate', function () {
        expect(OptimizedImageUrl::of(($this->storedImage)(generatedConversions: ['optimized' => false])))
            ->toEndWith('/42/banner.jpg'.$this->versionStamp);
    });

    it('ignores a conversion of another name, however many were generated', function () {
        expect(OptimizedImageUrl::of(($this->storedImage)(generatedConversions: ['thumbnail' => true])))
            ->toEndWith('/42/banner.jpg'.$this->versionStamp);
    });
});

describe('the first image of a collection', function () {
    it('answers with nothing for a collection holding no image', function () {
        expect(OptimizedImageUrl::firstOf(($this->ownerHolding)(), OptimizedImageOwnerModel::PHOTO_COLLECTION))->toBeNull();
    });

    it('answers with nothing when only another collection holds an image', function () {
        $owner = ($this->ownerHolding)(($this->storedImage)(collection: OptimizedImageOwnerModel::COVER_COLLECTION));

        expect(OptimizedImageUrl::firstOf($owner, OptimizedImageOwnerModel::PHOTO_COLLECTION))->toBeNull();
    });

    it('hands back the compressed copy of the image the collection holds', function () {
        $owner = ($this->ownerHolding)(($this->storedImage)());

        expect(OptimizedImageUrl::firstOf($owner, OptimizedImageOwnerModel::PHOTO_COLLECTION))
            ->toEndWith('/42/conversions/banner-optimized.webp'.$this->versionStamp);
    });

    it('falls back to the uploaded file of that image while no compressed copy exists', function () {
        $owner = ($this->ownerHolding)(($this->storedImage)(generatedConversions: []));

        expect(OptimizedImageUrl::firstOf($owner, OptimizedImageOwnerModel::PHOTO_COLLECTION))
            ->toEndWith('/42/banner.jpg'.$this->versionStamp);
    });

    it('takes the image in first position, not the one stored first', function () {
        $owner = ($this->ownerHolding)(
            ($this->storedImage)(key: 42, fileName: 'second.jpg', position: 2),
            ($this->storedImage)(key: 43, fileName: 'first.jpg', position: 1),
        );

        expect(OptimizedImageUrl::firstOf($owner, OptimizedImageOwnerModel::PHOTO_COLLECTION))
            ->toEndWith('/43/conversions/first-optimized.webp'.$this->versionStamp);
    });
});
