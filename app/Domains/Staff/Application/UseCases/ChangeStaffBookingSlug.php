<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\ChangeStaffBookingSlugInput;
use App\Domains\Staff\Application\Presenters\BookingLinkPresenter;
use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Exceptions\BookingSlugAlreadyTaken;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ChangeStaffBookingSlug
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly StaffProfileRepository $profiles,
        private readonly BookingSlugRegistry $bookingSlugs,
        private readonly BookingLinkPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BookingLinkData>
     */
    public function handle(ChangeStaffBookingSlugInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $member = $this->members->findForBusiness($businessId, $input->staffMemberId);
            $profile = $this->profiles->findForStaffMember($businessId, $member->id);
            $bookingSlug = $input->toBookingSlug();

            $profile->changeBookingSlug($bookingSlug);
            $this->ensureFreeFor($businessId, $member->id, $bookingSlug);

            $this->profiles->save($profile);

            return UseCaseResponse::success($this->presenter->linkOf($profile));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BookingSlugAlreadyTaken
     */
    private function ensureFreeFor(string $businessId, string $staffMemberId, BookingSlug $bookingSlug): void
    {
        if ($this->bookingSlugs->isHeldByAnother($businessId, $bookingSlug->value, $staffMemberId)) {
            throw BookingSlugAlreadyTaken::for($bookingSlug->value);
        }
    }
}
