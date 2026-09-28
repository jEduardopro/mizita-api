<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\GenerateStaffBookingLinkInput;
use App\Domains\Staff\Application\Presenters\BookingLinkPresenter;
use App\Domains\Staff\Application\Services\BookingReadinessAssessor;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Services\SlugAllocator;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class GenerateStaffBookingLink
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly StaffProfileRepository $profiles,
        private readonly BookingSlugRegistry $bookingSlugs,
        private readonly AccountDirectory $accounts,
        private readonly BookingReadinessAssessor $readiness,
        private readonly SlugAllocator $slugs,
        private readonly BookingLinkPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<BookingLinkData>
     */
    public function handle(GenerateStaffBookingLinkInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $member = $this->members->findForBusiness($businessId, $input->staffMemberId);
            $profile = $this->profiles->findForStaffMember($businessId, $member->id);

            $profile->assignBookingSlug(
                $this->freeSlugFor($member),
                $this->readiness->assess($businessId, $member->id),
            );

            $this->profiles->save($profile);

            return UseCaseResponse::success($this->presenter->linkOf($profile));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function freeSlugFor(StaffMember $member): BookingSlug
    {
        $base = BookingSlug::fromName($this->nameOf($member));

        return $this->slugs->allocate(
            $base,
            $this->bookingSlugs->slugsMatching($member->businessId, $base->value),
        );
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function nameOf(StaffMember $member): string
    {
        $account = $this->accounts->describe([$member->accountId])[0]
            ?? throw StaffMemberNotFound::forAccount($member->accountId);

        return $account->name;
    }
}
