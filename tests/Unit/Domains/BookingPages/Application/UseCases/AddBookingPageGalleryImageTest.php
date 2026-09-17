<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Application\UseCases\AddBookingPageGalleryImage;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeCurrentBookingPage;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->pages = new FakeCurrentBookingPage(BookingPageFixtures::page());
    $this->images = (new FakeBookingPageImages)->withPage(BookingPageFixtures::page());

    $this->useCase = new AddBookingPageGalleryImage(
        $this->pages,
        $this->images,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext,
    );

    $this->add = fn (...$overrides) => $this->useCase->handle(BookingPageFixtures::attachInput(...$overrides));
});

describe('adding an image to the gallery', function () {
    it('answers with the page carrying its enlarged gallery', function () {
        $data = ($this->add)()->value();

        expect($data->id)->toBe(BookingPageFixtures::PAGE_ID)
            ->and($data->gallery)->toHaveCount(1)
            ->and($data->gallery[0]->position)->toBe(1);
    });

    it('hands the media port the file and the page it belongs to', function () {
        ($this->add)(fileName: 'front.jpg');

        expect($this->images->galleryImagesAdded)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'bookingPageId' => BookingPageFixtures::PAGE_ID,
            'sourcePath' => BookingPageFixtures::SOURCE_PATH,
            'fileName' => 'front.jpg',
        ]]);
    });

    it('adds to the gallery a page already has rather than replacing it', function () {
        $this->images->withGalleryOf(BookingPageFixtures::page(), 3);

        expect(($this->add)()->value()->gallery)->toHaveCount(4);
    });

    it('uploads to the page of the business the caller is operating', function () {
        ($this->add)();

        expect($this->pages->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('leaves the banner alone', function () {
        $this->images->withBanner(BookingPageFixtures::page(), BookingPageFixtures::BANNER_URL);

        expect(($this->add)()->value()->bannerUrl)->toBe(BookingPageFixtures::BANNER_URL);
    });

    it('names every image in the answer by its uuid, never by a row number', function () {
        $gallery = ($this->add)()->value()->gallery;

        expect($gallery[0]->id)->toBeString()
            ->and($gallery[0]->id)->not->toBe('1')
            ->and($gallery[0]->id)->toMatch('/^[0-9a-f-]{36}$/');
    });
});

describe('a gallery that is already full', function () {
    it('holds twenty images at most', function () {
        expect(AddBookingPageGalleryImage::MAXIMUM_GALLERY_IMAGES)->toBe(20);
    });

    it('accepts the twentieth image', function () {
        $this->images->withGalleryOf(BookingPageFixtures::page(), 19);

        $response = ($this->add)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->gallery)->toHaveCount(20);
    });

    it('refuses the twenty first', function () {
        $this->images->withGalleryOf(BookingPageFixtures::page(), 20);

        $response = ($this->add)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_gallery_full')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('stores nothing once the gallery is full', function () {
        $this->images->withGalleryOf(BookingPageFixtures::page(), 20);

        ($this->add)();

        expect($this->images->galleryImagesAdded)->toBe([]);
    });

    it('says how many images a gallery holds', function () {
        $this->images->withGalleryOf(BookingPageFixtures::page(), 20);

        expect(fn () => ($this->add)()->value())
            ->toThrow('A booking page gallery holds up to [20] images.');
    });
});

describe('an upload it refuses', function () {
    it('refuses a file type the page cannot show', function () {
        $response = ($this->add)(mimeType: 'application/pdf');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unsupported_booking_page_image');
    });

    it('refuses a file past the size the collection accepts', function () {
        $response = ($this->add)(sizeInBytes: 6 * 1024 * 1024);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_image_too_large');
    });

    it('stores nothing when it refuses the upload', function () {
        ($this->add)(sourcePath: '   ');

        expect($this->images->galleryImagesAdded)->toBe([]);
    });

    it('does not even ask which page it is, when the upload is already refused', function () {
        ($this->add)(mimeType: 'application/pdf');

        expect($this->pages->businessIdsSeen)->toBe([]);
    });
});

describe('a gallery of another business', function () {
    beforeEach(function () {
        $this->otherPage = BookingPageFixtures::page(
            id: BookingPageFixtures::OTHER_PAGE_ID,
            businessId: BookingPageFixtures::OTHER_BUSINESS_ID,
        );
    });

    it('counts only the images of the business in context towards the limit', function () {
        $this->images->withGalleryOf($this->otherPage, 20);

        $response = ($this->add)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->gallery)->toHaveCount(1);
    });

    it('leaves the neighbour gallery exactly as it was', function () {
        $this->images->withGalleryOf($this->otherPage, 2);

        ($this->add)();

        expect($this->images->galleryFor(
            BookingPageFixtures::OTHER_BUSINESS_ID,
            BookingPageFixtures::OTHER_PAGE_ID,
        ))->toHaveCount(2);
    });

    it('refuses to add an image to a page the business in context does not own', function () {
        $useCase = new AddBookingPageGalleryImage(
            $this->pages,
            $this->images,
            new BookingPagePresenter($this->images),
            new FakeBusinessContext(BookingPageFixtures::OTHER_BUSINESS_ID),
        );

        $response = $useCase->handle(BookingPageFixtures::attachInput());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->images->galleryImagesAdded)->toBe([]);
    });
});
