<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\RemoveBookingPageGalleryImageInput;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Application\UseCases\RemoveBookingPageGalleryImage;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\BookingPages\BookingPageFixtures;
use Tests\Support\BookingPages\FakeBookingPageImages;
use Tests\Support\BookingPages\FakeBookingPageRepository;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->repository = (new FakeBookingPageRepository)->store(BookingPageFixtures::page());
    $this->images = (new FakeBookingPageImages)->withGallery(
        BookingPageFixtures::PAGE_ID,
        BookingPageFixtures::image(BookingPageFixtures::IMAGE_ID, position: 1),
        BookingPageFixtures::image(BookingPageFixtures::SECOND_IMAGE_ID, position: 2),
    );

    $this->useCase = new RemoveBookingPageGalleryImage(
        $this->repository,
        $this->images,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext,
    );

    $this->remove = fn (string $imageId = BookingPageFixtures::IMAGE_ID) => $this->useCase
        ->handle(new RemoveBookingPageGalleryImageInput($imageId));
});

describe('removing an image', function () {
    it('answers with the gallery that is left', function () {
        $data = ($this->remove)()->value();

        expect($data->gallery)->toHaveCount(1)
            ->and($data->gallery[0]->id)->toBe(BookingPageFixtures::SECOND_IMAGE_ID);
    });

    it('tells the media port which image of which page to drop', function () {
        ($this->remove)(BookingPageFixtures::SECOND_IMAGE_ID);

        expect($this->images->galleryImagesRemoved)->toBe([[
            'bookingPageId' => BookingPageFixtures::PAGE_ID,
            'imageId' => BookingPageFixtures::SECOND_IMAGE_ID,
        ]]);
    });

    it('reads the page of the business the caller is operating', function () {
        ($this->remove)();

        expect($this->repository->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('leaves the banner alone', function () {
        $this->images->withBanner(BookingPageFixtures::PAGE_ID, BookingPageFixtures::BANNER_URL);

        expect(($this->remove)()->value()->bannerUrl)->toBe(BookingPageFixtures::BANNER_URL);
    });
});

describe('an image it will not remove', function () {
    it('refuses an id that is not the uuid of an image', function (string $imageId) {
        $response = ($this->remove)($imageId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_image_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'a row number' => '7',
        'a truncated uuid' => '01930000-0000-7000-8000',
    ]);

    it('does not even ask which page it is, when the id is already refused', function () {
        ($this->remove)('7');

        expect($this->repository->businessIdsSeen)->toBe([])
            ->and($this->images->galleryImagesRemoved)->toBe([]);
    });

    it('refuses an image no gallery of this page holds', function () {
        $response = ($this->remove)(BookingPageFixtures::THIRD_IMAGE_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_image_not_found');
    });

    it('refuses when the business has no booking page at all', function () {
        $useCase = new RemoveBookingPageGalleryImage(
            new FakeBookingPageRepository,
            $this->images,
            new BookingPagePresenter($this->images),
            new FakeBusinessContext,
        );

        $response = $useCase->handle(new RemoveBookingPageGalleryImageInput(BookingPageFixtures::IMAGE_ID));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_not_found')
            ->and($this->images->galleryImagesRemoved)->toBe([]);
    });

    it('never removes an image from another business page', function () {
        $useCase = new RemoveBookingPageGalleryImage(
            $this->repository,
            $this->images,
            new BookingPagePresenter($this->images),
            new FakeBusinessContext(BookingPageFixtures::OTHER_BUSINESS_ID),
        );

        $response = $useCase->handle(new RemoveBookingPageGalleryImageInput(BookingPageFixtures::IMAGE_ID));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_not_found')
            ->and($this->images->galleryImagesRemoved)->toBe([]);
    });
});
