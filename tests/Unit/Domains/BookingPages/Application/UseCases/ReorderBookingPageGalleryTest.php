<?php

declare(strict_types=1);

use App\Domains\BookingPages\Application\Dtos\ReorderBookingPageGalleryInput;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Application\UseCases\ReorderBookingPageGallery;
use App\Domains\BookingPages\ValueObjects\BookingPageImage;
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
        BookingPageFixtures::image(BookingPageFixtures::THIRD_IMAGE_ID, position: 3),
    );

    $this->useCase = new ReorderBookingPageGallery(
        $this->repository,
        $this->images,
        new BookingPagePresenter($this->images),
        new FakeBusinessContext,
    );

    $this->reorder = fn (array $imageIds) => $this->useCase->handle(new ReorderBookingPageGalleryInput($imageIds));
});

describe('reordering the gallery', function () {
    it('answers with the gallery in the order that was asked for', function () {
        $data = ($this->reorder)([
            BookingPageFixtures::THIRD_IMAGE_ID,
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
        ])->value();

        expect(array_column($data->gallery, 'id'))->toBe([
            BookingPageFixtures::THIRD_IMAGE_ID,
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
        ]);
    });

    it('hands the media port the order and the page it belongs to', function () {
        ($this->reorder)([
            BookingPageFixtures::SECOND_IMAGE_ID,
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::THIRD_IMAGE_ID,
        ]);

        expect($this->images->reorders)->toBe([[
            'bookingPageId' => BookingPageFixtures::PAGE_ID,
            'imageIds' => [
                BookingPageFixtures::SECOND_IMAGE_ID,
                BookingPageFixtures::IMAGE_ID,
                BookingPageFixtures::THIRD_IMAGE_ID,
            ],
        ]]);
    });

    it('reads the page of the business the caller is operating', function () {
        ($this->reorder)([
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            BookingPageFixtures::THIRD_IMAGE_ID,
        ]);

        expect($this->repository->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('renumbers the whole gallery from the first position when the order is applied', function () {
        ($this->reorder)([
            BookingPageFixtures::THIRD_IMAGE_ID,
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
        ]);

        expect(array_map(
            static fn (BookingPageImage $image): int => $image->position,
            $this->images->galleryFor(BookingPageFixtures::PAGE_ID),
        ))->toBe([1, 2, 3]);
    });

    it('leaves the banner alone', function () {
        $this->images->withBanner(BookingPageFixtures::PAGE_ID, BookingPageFixtures::BANNER_URL);

        $data = ($this->reorder)([
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            BookingPageFixtures::THIRD_IMAGE_ID,
        ])->value();

        expect($data->bannerUrl)->toBe(BookingPageFixtures::BANNER_URL);
    });
});

describe('an order it will not apply', function () {
    it('refuses an empty order', function () {
        $response = ($this->reorder)([]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_gallery_order')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('refuses an order that names one image twice', function () {
        $response = ($this->reorder)([BookingPageFixtures::IMAGE_ID, BookingPageFixtures::IMAGE_ID]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_gallery_order');
    });

    it('refuses an order carrying something that is not the uuid of an image', function () {
        $response = ($this->reorder)([BookingPageFixtures::IMAGE_ID, '7']);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_image_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('does not even ask which page it is, when the order is already refused', function () {
        ($this->reorder)([]);

        expect($this->repository->businessIdsSeen)->toBe([])
            ->and($this->images->reorders)->toBe([]);
    });

    it('refuses an order naming an image this gallery does not hold', function () {
        $response = ($this->reorder)([
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            '01930000-0000-7000-8000-0000000000ff',
        ]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_image_not_found');
    });

    it('leaves the gallery in the order it already had when one uuid names no image of it', function () {
        ($this->reorder)([
            BookingPageFixtures::THIRD_IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            '01930000-0000-7000-8000-0000000000ff',
        ]);

        $gallery = $this->images->galleryFor(BookingPageFixtures::PAGE_ID);

        expect(array_map(static fn (BookingPageImage $image): string => $image->id, $gallery))->toBe([
            BookingPageFixtures::IMAGE_ID,
            BookingPageFixtures::SECOND_IMAGE_ID,
            BookingPageFixtures::THIRD_IMAGE_ID,
        ])
            ->and(array_map(static fn (BookingPageImage $image): int => $image->position, $gallery))->toBe([1, 2, 3]);
    });

    it('refuses an order that leaves an image of the gallery out', function () {
        $response = ($this->reorder)([BookingPageFixtures::IMAGE_ID]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_gallery_order');
    });

    it('refuses when the business has no booking page at all', function () {
        $useCase = new ReorderBookingPageGallery(
            new FakeBookingPageRepository,
            $this->images,
            new BookingPagePresenter($this->images),
            new FakeBusinessContext,
        );

        $response = $useCase->handle(new ReorderBookingPageGalleryInput([BookingPageFixtures::IMAGE_ID]));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_not_found')
            ->and($this->images->reorders)->toBe([]);
    });

    it('never reorders another business gallery', function () {
        $useCase = new ReorderBookingPageGallery(
            $this->repository,
            $this->images,
            new BookingPagePresenter($this->images),
            new FakeBusinessContext(BookingPageFixtures::OTHER_BUSINESS_ID),
        );

        $response = $useCase->handle(new ReorderBookingPageGalleryInput([BookingPageFixtures::IMAGE_ID]));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('booking_page_not_found')
            ->and($this->images->reorders)->toBe([]);
    });
});
