<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\ReorderBookingPageGalleryInput;
use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;

describe('building the order from a payload', function () {
    it('takes the images the caller listed, in the order they were listed', function () {
        $input = ReorderBookingPageGalleryInput::fromRequest([
            'images' => [BookingPageFixtures::SECOND_IMAGE_ID, BookingPageFixtures::IMAGE_ID],
        ]);

        expect($input->imageIds)->toBe([BookingPageFixtures::SECOND_IMAGE_ID, BookingPageFixtures::IMAGE_ID]);
    });

    it('survives a payload with no images key at all', function () {
        expect(ReorderBookingPageGalleryInput::fromRequest([])->imageIds)->toBe([]);
    });

    it('survives a payload whose images key is not a list', function (mixed $images) {
        expect(ReorderBookingPageGalleryInput::fromRequest(['images' => $images])->imageIds)->toBe([]);
    })->with([
        'null' => [null],
        'a string' => ['one'],
        'a number' => [7],
        'a boolean' => [true],
    ]);

    it('turns a value that is not a string into one the validation will refuse', function () {
        $input = ReorderBookingPageGalleryInput::fromRequest(['images' => [42, null]]);

        expect($input->imageIds)->toBe(['', ''])
            ->and(fn () => $input->validate())->toThrow(InvalidGalleryOrder::class);
    });

    it('renumbers the list, so a payload with gaps still arrives as a list', function () {
        $input = ReorderBookingPageGalleryInput::fromRequest([
            'images' => [3 => BookingPageFixtures::IMAGE_ID, 7 => BookingPageFixtures::SECOND_IMAGE_ID],
        ]);

        expect(array_keys($input->imageIds))->toBe([0, 1]);
    });
});

describe('an order it accepts', function () {
    it('lets a list of distinct image uuids through', function () {
        $input = new ReorderBookingPageGalleryInput([
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            BookingPageFixtures::THIRD_IMAGE_ID,
        ]);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('lets a gallery of a single image through', function () {
        expect(fn () => (new ReorderBookingPageGalleryInput([BookingPageFixtures::IMAGE_ID]))->validate())
            ->not->toThrow(Throwable::class);
    });
});

describe('an order it refuses', function () {
    it('refuses an empty order, because a gallery order lists every image', function () {
        expect(fn () => (new ReorderBookingPageGalleryInput([]))->validate())
            ->toThrow(InvalidGalleryOrder::class, 'A gallery order must list every image in the gallery exactly once.');
    });

    it('refuses an order that names one image twice', function () {
        expect(fn () => (new ReorderBookingPageGalleryInput([
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            BookingPageFixtures::IMAGE_ID,
        ]))->validate())->toThrow(InvalidGalleryOrder::class);
    });

    it('refuses an order carrying something that is not the uuid of an image', function (string $imageId) {
        expect(fn () => (new ReorderBookingPageGalleryInput([BookingPageFixtures::IMAGE_ID, $imageId]))->validate())
            ->toThrow(BookingPageImageNotFound::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a row number' => '7',
        'a truncated uuid' => '01930000-0000-7000-8000',
        'a uuid with a letter out of range' => '01930000-0000-7000-8000-0000000000g1',
    ]);

    it('classifies a broken order as something the caller has to correct', function () {
        $failure = InvalidGalleryOrder::incomplete();

        expect($failure->errorCode())->toBe('invalid_gallery_order')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('counts the images before it reads them, so a duplicate is an order problem', function () {
        expect(fn () => (new ReorderBookingPageGalleryInput(['7', '7']))->validate())
            ->toThrow(InvalidGalleryOrder::class);
    });
});
