<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\UseCases;

use App\Domains\Accounts\Application\Dtos\AccountDeletionPreviewData;
use App\Domains\Accounts\Application\Dtos\PreviewAccountDeletionInput;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Domains\Accounts\Contracts\TeamMemberships;
use App\Domains\Accounts\Contracts\UpcomingBookings;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\DeletionBlocker;
use App\Domains\Accounts\ValueObjects\DeletionGracePeriod;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class PreviewAccountDeletion
{
    private const NO_UPCOMING_APPOINTMENTS = 0;

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly TeamMemberships $memberships,
        private readonly OwnedBusinesses $ownedBusinesses,
        private readonly UpcomingBookings $upcomingBookings,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<AccountDeletionPreviewData>
     */
    public function handle(PreviewAccountDeletionInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $preview = $this->preview($this->accounts->findById($input->accountId));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($preview);
    }

    private function preview(Account $account): AccountDeletionPreviewData
    {
        $ownedBusinessId = $this->memberships->ownedBusinessIdOf($account->id);

        return new AccountDeletionPreviewData(
            email: $account->email(),
            hasPassword: $account->holdsPassword(),
            ownedBusiness: $ownedBusinessId === null ? null : $this->ownedBusinesses->describe($ownedBusinessId),
            upcomingAppointmentsCount: $ownedBusinessId === null
                ? self::NO_UPCOMING_APPOINTMENTS
                : $this->upcomingBookings->countForBusiness($ownedBusinessId),
            blockedBy: $this->blockerFor($account),
            gracePeriodEndsAt: DeletionGracePeriod::endingFrom($this->clock->now()),
        );
    }

    private function blockerFor(Account $account): ?DeletionBlocker
    {
        if ($this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness($account->id)) {
            return DeletionBlocker::UpcomingAppointments;
        }

        return null;
    }
}
