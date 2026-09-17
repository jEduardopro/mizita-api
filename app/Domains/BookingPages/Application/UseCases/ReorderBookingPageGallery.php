<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\UseCases;

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Dtos\ReorderBookingPageGalleryInput;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ReorderBookingPageGallery
{
    public function __construct(
        private readonly BookingPageRepository $pages,
        private readonly BookingPageImages $images,
        private readonly BookingPagePresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BookingPageData>
     */
    public function handle(ReorderBookingPageGalleryInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $page = $this->pages->ofBusiness($businessId);

            $this->images->reorderGallery($businessId, $page->id, $input->imageIds);

            return UseCaseResponse::success($this->presenter->describe($page));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
