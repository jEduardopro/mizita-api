<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\UseCases;

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class RemoveBookingPageBanner
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
    public function handle(): UseCaseResponse
    {
        try {
            $page = $this->pages->ofBusiness($this->business->currentBusinessId());

            $this->images->removeBanner($page->id);

            return UseCaseResponse::success($this->presenter->describe($page));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
