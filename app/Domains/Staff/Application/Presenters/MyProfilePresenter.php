<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Presenters;

use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\AccountSnapshot;

final class MyProfilePresenter
{
    public function __construct(
        private readonly AccountDirectory $accounts,
        private readonly StaffPhoneBook $phones,
        private readonly StaffProfilePhotos $photos,
    ) {}

    /**
     * @throws StaffMemberNotFound
     */
    public function describe(StaffMember $member, StaffProfile $profile): MyProfileData
    {
        return MyProfileData::fromEntities(
            $member,
            $profile,
            $this->accountOf($member),
            $this->phones->forProfile($profile->id),
            $this->photos->urlFor($profile->businessId, $profile->id),
        );
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function accountOf(StaffMember $member): AccountSnapshot
    {
        $account = $this->accounts->describe([$member->accountId])[0] ?? null;

        if ($account === null) {
            throw StaffMemberNotFound::forAccount($member->accountId);
        }

        return $account;
    }
}
