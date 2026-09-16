<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\UseCases;

use App\Domains\BookingPages\Application\Dtos\BookingPageData;
use App\Domains\BookingPages\Application\Presenters\BookingPagePresenter;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;

final class EnsureBookingPage
{
    public function __construct(
        private readonly CurrentBookingPage $pages,
        private readonly BookingPagePresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BookingPageData>
     */
    public function handle(): UseCaseResponse
    {
        $page = $this->pages->forBusiness($this->business->currentBusinessId());

        return UseCaseResponse::success($this->presenter->describe($page));
    }
}
