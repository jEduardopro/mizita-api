<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Application\UseCases\AttachBookingPageBanner;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeCurrentBookingPage;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->pages = new FakeCurrentBookingPage(BookingPageFixtures::page());
    $this->images = new FakeBookingPageImages;

    $this->useCase = new AttachBookingPageBanner(
        $this->pages,
        $this->images,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext,
    );

    $this->attach = fn (...$overrides) => $this->useCase->handle(BookingPageFixtures::attachInput(...$overrides));
});

describe('uploading a banner', function () {
    it('answers with the page carrying its new banner', function () {
        $data = ($this->attach)()->value();

        expect($data->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and($data->bannerUrl)->toBe(
                'https://cdn.mizita.test/booking-pages/'.BookingPageFixtures::PAGE_ID.'/banner.jpg',
            );
    });

    it('hands the media port the file and the page it belongs to', function () {
        ($this->attach)();

        expect($this->images->bannersReplaced)->toBe([[
            'bookingPageId' => BookingPageFixtures::PAGE_ID,
            'sourcePath' => BookingPageFixtures::SOURCE_PATH,
            'fileName' => BookingPageFixtures::FILE_NAME,
        ]]);
    });

    it('replaces the banner instead of adding a second one', function () {
        $this->images->withBanner(BookingPageFixtures::PAGE_ID, 'https://cdn.mizita.test/old.jpg');

        expect(($this->attach)()->value()->bannerUrl)->not->toBe('https://cdn.mizita.test/old.jpg')
            ->and($this->images->bannersReplaced)->toHaveCount(1);
    });

    it('uploads to the page of the business the caller is operating', function () {
        ($this->attach)();

        expect($this->pages->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('leaves the gallery alone', function () {
        $this->images->withGalleryOf(BookingPageFixtures::PAGE_ID, 3);

        expect(($this->attach)()->value()->gallery)->toHaveCount(3);
    });
});

describe('an upload it refuses', function () {
    it('refuses a file type the page cannot show', function () {
        $response = ($this->attach)(mimeType: 'application/pdf');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unsupported_booking_page_image')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a file past the size the collection accepts', function () {
        $response = ($this->attach)(sizeInBytes: 6 * 1024 * 1024);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_image_too_large')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('stores nothing when it refuses the upload', function () {
        ($this->attach)(mimeType: 'application/pdf');

        expect($this->images->bannersReplaced)->toBe([]);
    });

    it('does not even ask which page it is, when the upload is already refused', function () {
        ($this->attach)(sourcePath: '');

        expect($this->pages->businessIdsSeen)->toBe([]);
    });

    it('returns the refusal instead of throwing it at the caller', function () {
        expect(fn () => ($this->attach)(mimeType: 'image/gif'))->not->toThrow(Throwable::class);
    });
});
