<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\MyProfileData;
use App\Domains\Staff\Application\Dtos\ProfilePhoneInput;
use App\Domains\Staff\Application\Dtos\UpdateMyProfileInput;
use App\Domains\Staff\Application\Presenters\MyProfilePresenter;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

final class UpdateMyProfile
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly StaffProfileRepository $profiles,
        private readonly AccountDirectory $accounts,
        private readonly StaffPhoneBook $phones,
        private readonly MyProfilePresenter $presenter,
        private readonly PhoneNumberParser $phoneNumberParser,
        private readonly BusinessContext $business,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<MyProfileData>
     */
    public function handle(UpdateMyProfileInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $member = $this->members->findForAccount($businessId, $input->accountId);
            $profile = $this->profiles->findForStaffMember($businessId, $member->id);
            $phone = $this->parsedPhone($input->phone);

            $profile->describe($input->toJobTitle(), $input->toAbout());

            $this->transactions->run(function () use ($input, $member, $profile, $phone): void {
                $this->apply($input, $member, $profile, $phone);
            });

            return UseCaseResponse::success($this->presenter->describe($member, $profile));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws StaffMemberNotFound
     * @throws InvalidProfileName
     */
    private function apply(
        UpdateMyProfileInput $input,
        StaffMember $member,
        StaffProfile $profile,
        ?PhoneNumber $phone,
    ): void {
        $this->accounts->rename($member->accountId, $input->name);
        $this->profiles->save($profile);
        $this->phones->replaceForProfile($profile->id, $phone);
    }

    /**
     * @throws InvalidProfilePhone
     */
    private function parsedPhone(?ProfilePhoneInput $submitted): ?PhoneNumber
    {
        if ($submitted === null) {
            return null;
        }

        $country = CountryCode::tryFrom(mb_strtoupper(trim($submitted->countryCode)));

        if ($country === null) {
            throw InvalidProfilePhone::inCountry($submitted->countryCode);
        }

        $number = $this->phoneNumberParser->parse($country, $submitted->nationalNumber);

        if ($number === null) {
            throw InvalidProfilePhone::forCountry($country);
        }

        return $number;
    }
}
