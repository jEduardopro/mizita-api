<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\AttachBookingPageImageInput;
use App\Domains\BookingPages\Exceptions\BookingPageImageTooLarge;
use App\Domains\BookingPages\Exceptions\UnsupportedBookingPageImage;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;

describe('the limits the upload is held to', function () {
    it('accepts an image of up to five megabytes', function () {
        expect(AttachBookingPageImageInput::MAXIMUM_BYTES)->toBe(5 * 1024 * 1024);
    });

    it('accepts the three image types the media collection declares', function () {
        expect(AttachBookingPageImageInput::ACCEPTED_MIME_TYPES)
            ->toBe(['image/jpeg', 'image/png', 'image/webp']);
    });
});

describe('an upload it accepts', function () {
    it('lets a well formed upload through', function () {
        expect(fn () => BookingPageFixtures::attachInput()->validate())->not->toThrow(Throwable::class);
    });

    it('lets every accepted type through', function (string $mimeType) {
        expect(fn () => BookingPageFixtures::attachInput(mimeType: $mimeType)->validate())
            ->not->toThrow(Throwable::class);
    })->with(AttachBookingPageImageInput::ACCEPTED_MIME_TYPES);

    it('lets an image of exactly the maximum size through', function () {
        expect(fn () => BookingPageFixtures::attachInput(
            sizeInBytes: AttachBookingPageImageInput::MAXIMUM_BYTES,
        )->validate())->not->toThrow(Throwable::class);
    });

    it('lets an image of a single byte through', function () {
        expect(fn () => BookingPageFixtures::attachInput(sizeInBytes: 1)->validate())->not->toThrow(Throwable::class);
    });

    it('holds the four facts it was handed', function () {
        $input = BookingPageFixtures::attachInput();

        expect($input->sourcePath)->toBe(BookingPageFixtures::SOURCE_PATH)
            ->and($input->fileName)->toBe(BookingPageFixtures::FILE_NAME)
            ->and($input->mimeType)->toBe(BookingPageFixtures::MIME_TYPE)
            ->and($input->sizeInBytes)->toBe(1024);
    });
});

describe('an upload it refuses', function () {
    it('refuses an upload that carries no file', function (string $sourcePath) {
        expect(fn () => BookingPageFixtures::attachInput(sourcePath: $sourcePath)->validate())
            ->toThrow(UnsupportedBookingPageImage::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
    ]);

    it('refuses a type the page cannot show', function (string $mimeType) {
        expect(fn () => BookingPageFixtures::attachInput(mimeType: $mimeType)->validate())
            ->toThrow(UnsupportedBookingPageImage::class);
    })->with([
        'a pdf' => 'application/pdf',
        'a svg, which can carry a script' => 'image/svg+xml',
        'a gif' => 'image/gif',
        'a heic' => 'image/heic',
        'nothing at all' => '',
        'the right type in the wrong case' => 'IMAGE/JPEG',
        'the right type padded' => ' image/jpeg ',
    ]);

    it('refuses an empty file', function (int $sizeInBytes) {
        expect(fn () => BookingPageFixtures::attachInput(sizeInBytes: $sizeInBytes)->validate())
            ->toThrow(UnsupportedBookingPageImage::class);
    })->with([
        'no bytes' => 0,
        'a negative count' => -1,
    ]);

    it('refuses an image one byte past the maximum', function () {
        expect(fn () => BookingPageFixtures::attachInput(
            sizeInBytes: AttachBookingPageImageInput::MAXIMUM_BYTES + 1,
        )->validate())->toThrow(BookingPageImageTooLarge::class);
    });

    it('says how big the image was and how big it may be', function () {
        expect(fn () => BookingPageFixtures::attachInput(sizeInBytes: 6_000_000)->validate())
            ->toThrow(
                BookingPageImageTooLarge::class,
                'A booking page image takes up to [5242880] bytes, got [6000000].',
            );
    });

    it('checks the file before it checks its size, so an empty upload reads as missing', function () {
        expect(fn () => (new AttachBookingPageImageInput('', 'banner.jpg', 'application/pdf', 0))->validate())
            ->toThrow(UnsupportedBookingPageImage::class, 'No image was offered.');
    });

    it('classifies both refusals as something the caller has to correct', function () {
        expect(UnsupportedBookingPageImage::ofType('application/pdf')->kind())->toBe(DomainFailureKind::Invalid)
            ->and(BookingPageImageTooLarge::atBytes(1, 0)->kind())->toBe(DomainFailureKind::Invalid);
    });
});
