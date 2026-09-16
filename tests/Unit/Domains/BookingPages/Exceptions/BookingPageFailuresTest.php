<?php

declare(strict_types=1);

use App\Domains\BookingPages\Exceptions\BookingPageGalleryFull;
use App\Domains\BookingPages\Exceptions\BookingPageImageNotFound;
use App\Domains\BookingPages\Exceptions\BookingPageImageTooLarge;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;
use App\Domains\BookingPages\Exceptions\InvalidBookingPageAccentColor;
use App\Domains\BookingPages\Exceptions\InvalidBookingPageButtonShape;
use App\Domains\BookingPages\Exceptions\InvalidBookingPageTheme;
use App\Domains\BookingPages\Exceptions\InvalidGalleryOrder;
use App\Domains\BookingPages\Exceptions\UnsupportedBookingPageImage;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;

/**
 * @return array<string, array{DomainFailure, string, DomainFailureKind}>
 */
function bookingPageFailures(): array
{
    return [
        'a full gallery' => [
            BookingPageGalleryFull::atLimit(20),
            'booking_page_gallery_full',
            DomainFailureKind::Conflict,
        ],
        'an image nobody has' => [
            BookingPageImageNotFound::withId(BookingPageFixtures::IMAGE_ID),
            'booking_page_image_not_found',
            DomainFailureKind::NotFound,
        ],
        'an image past the size limit' => [
            BookingPageImageTooLarge::atBytes(6_000_000, 5_242_880),
            'booking_page_image_too_large',
            DomainFailureKind::Invalid,
        ],
        'a business with no page' => [
            BookingPageNotFound::forBusiness('01930000-0000-7000-8000-0000000000b1'),
            'booking_page_not_found',
            DomainFailureKind::NotFound,
        ],
        'a colour off the palette' => [
            InvalidBookingPageAccentColor::withValue('fuchsia'),
            'invalid_booking_page_accent_color',
            DomainFailureKind::Invalid,
        ],
        'a shape the page cannot render' => [
            InvalidBookingPageButtonShape::withValue('square'),
            'invalid_booking_page_button_shape',
            DomainFailureKind::Invalid,
        ],
        'a theme the page cannot wear' => [
            InvalidBookingPageTheme::withValue('sepia'),
            'invalid_booking_page_theme',
            DomainFailureKind::Invalid,
        ],
        'a broken gallery order' => [
            InvalidGalleryOrder::incomplete(),
            'invalid_gallery_order',
            DomainFailureKind::Invalid,
        ],
        'an image type the page cannot show' => [
            UnsupportedBookingPageImage::ofType('application/pdf'),
            'unsupported_booking_page_image',
            DomainFailureKind::Invalid,
        ],
        'an upload with no file' => [
            UnsupportedBookingPageImage::missing(),
            'unsupported_booking_page_image',
            DomainFailureKind::Invalid,
        ],
    ];
}

it('answers with the stable error code the client is shown a sentence for', function (
    DomainFailure $failure,
    string $code,
) {
    expect($failure->errorCode())->toBe($code);
})->with(bookingPageFailures());

it('classifies the refusal so the edge knows which status to render', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
) {
    expect($failure->kind())->toBe($kind);
})->with(bookingPageFailures());

it('carries the interface the renderer is registered against', function (DomainFailure $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure)->toBeInstanceOf(Throwable::class);
})->with(bookingPageFailures());

it('has a sentence to show the caller in every locale', function (
    DomainFailure $failure,
    string $code,
    DomainFailureKind $kind,
    string $locale,
) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with(bookingPageFailures())->with(['en', 'es']);

it('says what it turned down and nothing more', function () {
    expect(BookingPageGalleryFull::atLimit(20)->getMessage())
        ->toBe('A booking page gallery holds up to [20] images.')
        ->and(BookingPageImageNotFound::withId('42')->getMessage())
        ->toBe('Booking page image [42] is not on record.')
        ->and(BookingPageImageTooLarge::atBytes(6, 5)->getMessage())
        ->toBe('A booking page image takes up to [5] bytes, got [6].')
        ->and(BookingPageNotFound::forBusiness('b-1')->getMessage())
        ->toBe('Business [b-1] has no booking page.')
        ->and(UnsupportedBookingPageImage::missing()->getMessage())
        ->toBe('No image was offered.');
});

it('tells a page that does not exist apart from an image that does not', function () {
    expect(BookingPageNotFound::forBusiness('b-1')->errorCode())
        ->not->toBe(BookingPageImageNotFound::withId('i-1')->errorCode());
});

it('reads a full gallery as a conflict and an oversized image as a correction', function () {
    expect(BookingPageGalleryFull::atLimit(20)->kind())->toBe(DomainFailureKind::Conflict)
        ->and(BookingPageImageTooLarge::atBytes(6, 5)->kind())->toBe(DomainFailureKind::Invalid);
});

it('shares one error code between the two ways an upload can be unsupported', function () {
    expect(UnsupportedBookingPageImage::missing()->errorCode())
        ->toBe(UnsupportedBookingPageImage::ofType('application/pdf')->errorCode());
});
