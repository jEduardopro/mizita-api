<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\RemoveBookingPageGalleryImageInput;
use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;

it('accepts the uuid of an image', function () {
    $input = new RemoveBookingPageGalleryImageInput(BookingPageFixtures::IMAGE_ID);

    expect(fn () => $input->validate())->not->toThrow(Throwable::class)
        ->and($input->imageId)->toBe(BookingPageFixtures::IMAGE_ID);
});

it('accepts a uuid written in upper case', function () {
    expect(fn () => (new RemoveBookingPageGalleryImageInput(
        mb_strtoupper(BookingPageFixtures::IMAGE_ID),
    ))->validate())->not->toThrow(Throwable::class);
});

it('refuses anything that is not the uuid of an image', function (string $imageId) {
    expect(fn () => (new RemoveBookingPageGalleryImageInput($imageId))->validate())
        ->toThrow(BookingPageImageNotFound::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'a row number' => '42',
    'a uuid with a letter out of range' => '01930000-0000-7000-8000-0000000000g1',
    'a uuid missing a group' => '01930000-0000-7000-0000000000c1',
    'a uuid with no dashes' => '019300000000700080000000000000c1',
    'a uuid with something glued on' => BookingPageFixtures::IMAGE_ID.' ',
    'a sentence' => 'the first one',
]);

it('classifies an image it cannot name as a 404, because that image is not on record', function () {
    $failure = BookingPageImageNotFound::withId('42');

    expect($failure->errorCode())->toBe('booking_page_image_not_found')
        ->and($failure->kind())->toBe(DomainFailureKind::NotFound);
});

it('names the id it turned down', function () {
    expect(fn () => (new RemoveBookingPageGalleryImageInput('42'))->validate())
        ->toThrow(BookingPageImageNotFound::class, 'Booking page image [42] is not on record.');
});
