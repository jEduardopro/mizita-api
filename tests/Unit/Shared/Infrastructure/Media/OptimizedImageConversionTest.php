<?php

declare(strict_types=1);

use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Shared\Infrastructure\Eloquent\Models\MediaModel;
use App\Shared\Infrastructure\Media\OptimizedImageConversion;
use App\Shared\Infrastructure\Media\OptimizedImageUrl;
use Illuminate\Support\Facades\Queue;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Tests\Support\Media\OptimizedImageOwnerModel;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return list<Conversion>
 */
function optimizedConversionsOf(HasMedia $owner): array
{
    return array_values(array_filter(
        $owner->mediaConversions,
        static fn (Conversion $conversion): bool => $conversion->getName() === OptimizedImageConversion::NAME,
    ));
}

function queuedOptimizableImage(string $collection = OptimizedImageOwnerModel::PHOTO_COLLECTION): MediaModel
{
    $media = (new MediaModel)->forceFill([
        'id' => 42,
        'model_type' => OptimizedImageOwnerModel::class,
        'model_id' => 1,
        'collection_name' => $collection,
        'name' => 'banner',
        'file_name' => 'banner.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'conversions_disk' => 'public',
        'size' => 1024,
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
        'order_column' => 1,
        'updated_at' => '2026-01-01 12:00:00',
    ]);
    $media->exists = true;

    return $media;
}

describe('the conversion it registers', function () {
    beforeEach(function () {
        $this->owner = new OptimizedImageOwnerModel;

        OptimizedImageConversion::register(
            $this->owner,
            1200,
            OptimizedImageOwnerModel::PHOTO_COLLECTION,
            OptimizedImageOwnerModel::COVER_COLLECTION,
        );

        $this->conversion = $this->owner->mediaConversions[0];
    });

    it('registers exactly one conversion, under the name every url reader asks for', function () {
        expect($this->owner->mediaConversions)->toHaveCount(1)
            ->and($this->conversion->getName())->toBe('optimized')
            ->and(OptimizedImageConversion::NAME)->toBe('optimized');
    });

    it('writes webp whatever the uploaded format was', function (string $uploadedExtension) {
        expect($this->conversion->getResultExtension($uploadedExtension))->toBe('webp')
            ->and($this->conversion->shouldKeepOriginalImageFormat())->toBeFalse();
    })->with(['jpg', 'jpeg', 'png', 'webp']);

    it('compresses at quality 80', function () {
        expect($this->conversion->getManipulations()->getFirstManipulationArgument('quality'))->toBe(80);
    });

    it('fits the image inside a square of the maximum dimension without upscaling or cropping it', function () {
        expect($this->conversion->getManipulations()->getManipulationArgument('fit'))->toBe([Fit::Max, 1200, 1200]);
    });

    it('runs on the queue rather than while the upload is stored, so the upload request does not wait for it', function () {
        expect($this->conversion->shouldBeQueued())->toBeTrue()
            ->and($this->conversion->shouldBeDeferred())->toBeFalse();
    });

    it('runs no external optimizer binary over the result', function () {
        expect($this->conversion->getManipulations()->toArray())->not->toHaveKey('optimize');
    });

    it('runs on exactly the collections it was given', function () {
        expect($this->conversion->getPerformOnCollections())
            ->toBe([OptimizedImageOwnerModel::PHOTO_COLLECTION, OptimizedImageOwnerModel::COVER_COLLECTION])
            ->and($this->conversion->shouldBePerformedOn('default'))->toBeFalse()
            ->and($this->conversion->shouldBePerformedOn('documents'))->toBeFalse();
    });

    it('takes the maximum dimension it was given, rather than a fixed one', function () {
        $owner = new OptimizedImageOwnerModel;

        OptimizedImageConversion::register($owner, 300, OptimizedImageOwnerModel::PHOTO_COLLECTION);

        expect($owner->mediaConversions[0]->getManipulations()->getManipulationArgument('fit'))->toBe([Fit::Max, 300, 300])
            ->and($owner->mediaConversions[0]->getPerformOnCollections())->toBe([OptimizedImageOwnerModel::PHOTO_COLLECTION]);
    });
});

describe('the models that compress what they store', function () {
    it('registers the optimized conversion with its own maximum dimension, on its own collections', function (string $modelClass, int $maximumDimension, array $collections) {
        $model = new $modelClass;
        $model->registerAllMediaConversions();

        $conversions = optimizedConversionsOf($model);

        expect($conversions)->toHaveCount(1)
            ->and($conversions[0]->getResultExtension('jpg'))->toBe('webp')
            ->and($conversions[0]->getManipulations()->getManipulationArgument('fit'))
            ->toBe([Fit::Max, $maximumDimension, $maximumDimension])
            ->and($conversions[0]->getPerformOnCollections())->toBe($collections)
            ->and($conversions[0]->shouldBeQueued())->toBeTrue();
    })->with('media owning models');

    it('names only collections the model actually declares', function (string $modelClass, int $maximumDimension, array $collections) {
        $model = new $modelClass;
        $model->registerAllMediaConversions();

        $declared = array_map(
            static fn (MediaCollection $collection): string => $collection->name,
            $model->mediaCollections,
        );

        expect(array_values(array_diff($collections, $declared)))->toBe([]);
    })->with('media owning models');
});

describe('a stored image whose compressed copy waits on the queue', function () {
    beforeEach(function () {
        Queue::fake();

        $this->versionStamp = '?v='.(new DateTimeImmutable('2026-01-01 12:00:00 UTC'))->getTimestamp();
        $this->fileManipulator = new FileManipulator;
    });

    it('pushes the conversion job instead of converting while the upload is stored', function () {
        $this->fileManipulator->createDerivedFiles(queuedOptimizableImage());

        Queue::assertPushed(PerformConversionsJob::class, 1);
    });

    it('queues the optimized conversion of the image that was stored, and nothing else', function () {
        $image = queuedOptimizableImage();

        $this->fileManipulator->createDerivedFiles($image);

        Queue::assertPushed(PerformConversionsJob::class, function (PerformConversionsJob $job) use ($image): bool {
            $conversionNames = (fn (): array => $this->conversions->map(
                static fn (Conversion $conversion): string => $conversion->getName(),
            )->values()->all())->call($job);

            return $conversionNames === [OptimizedImageConversion::NAME]
                && (fn (): object => $this->media)->call($job) === $image;
        });
    });

    it('holds the job back until the transaction storing the image has committed', function () {
        $this->fileManipulator->createDerivedFiles(queuedOptimizableImage());

        Queue::assertPushed(PerformConversionsJob::class, fn (PerformConversionsJob $job): bool => $job->afterCommit === true);
    });

    it('queues nothing for an image in a collection the conversion does not cover', function () {
        $this->fileManipulator->createDerivedFiles(queuedOptimizableImage(collection: 'default'));

        Queue::assertNotPushed(PerformConversionsJob::class);
    });

    it('keeps handing out the original upload while the job has not run', function () {
        $image = queuedOptimizableImage();

        $this->fileManipulator->createDerivedFiles($image);

        expect(OptimizedImageUrl::of($image))
            ->toEndWith('/42/banner.jpg'.$this->versionStamp)
            ->not->toContain('-optimized.webp');
    });
});

dataset('media owning models', [
    'the booking page banner and gallery' => [
        BookingPageModel::class,
        1920,
        [BookingPageModel::BANNER_COLLECTION, BookingPageModel::GALLERY_COLLECTION],
    ],
    'the service image' => [ServiceModel::class, 800, [ServiceModel::IMAGE_COLLECTION]],
    'the business logo' => [BusinessModel::class, 512, [BusinessModel::LOGO_COLLECTION]],
    'the staff profile photo' => [StaffProfileModel::class, 512, [StaffProfileModel::PHOTO_COLLECTION]],
    'the customer photo' => [CustomerModel::class, 512, [CustomerModel::PHOTO_COLLECTION]],
]);
