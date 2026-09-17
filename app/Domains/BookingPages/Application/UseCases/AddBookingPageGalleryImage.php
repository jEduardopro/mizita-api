<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\UseCases;

use App\Domains\BookingPages\Application\Dtos\AttachBookingPageImageInput;
use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\Exceptions\BookingPageGalleryFull;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class AddBookingPageGalleryImage
{
    public const MAXIMUM_GALLERY_IMAGES = 20;

    public function __construct(
        private readonly CurrentBookingPage $pages,
        private readonly BookingPageImages $images,
        private readonly BookingPagePresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BookingPageData>
     */
    public function handle(AttachBookingPageImageInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $page = $this->pages->forBusiness($businessId);

            $this->refuseFullGallery($businessId, $page->id);

            $this->images->addGalleryImage($businessId, $page->id, $input->sourcePath, $input->fileName);

            return UseCaseResponse::success($this->presenter->describe($page));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BookingPageGalleryFull
     */
    private function refuseFullGallery(string $businessId, string $bookingPageId): void
    {
        if (count($this->images->galleryFor($businessId, $bookingPageId)) >= self::MAXIMUM_GALLERY_IMAGES) {
            throw BookingPageGalleryFull::atLimit(self::MAXIMUM_GALLERY_IMAGES);
        }
    }
}
