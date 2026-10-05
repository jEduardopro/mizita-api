<?php

declare(strict_types=1);

use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Shared\Infrastructure\Media\OptimizedImageConversion;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Conversion;
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

    it('runs while the upload is stored, so the url handed back already points at a file that exists', function () {
        expect($this->conversion->shouldBeQueued())->toBeFalse()
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
            ->and($conversions[0]->shouldBeQueued())->toBeFalse();
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
