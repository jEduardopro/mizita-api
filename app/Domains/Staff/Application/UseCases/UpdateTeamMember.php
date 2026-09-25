<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\ProfilePhoneInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Dtos\UpdateTeamMemberInput;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Contracts\TeamAccountProvisioner;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\InvalidProfileAbout;
use App\Domains\Staff\Exceptions\InvalidProfileJobTitle;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\OwnerLevelIsFixed;
use App\Domains\Staff\ValueObjects\AccessTransition;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Contracts\Events\Dispatcher;

final class UpdateTeamMember
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly StaffProfileRepository $profiles,
        private readonly AccountDirectory $accounts,
        private readonly TeamAccountProvisioner $provisioner,
        private readonly StaffPhoneBook $phones,
        private readonly TeamMemberPresenter $presenter,
        private readonly PhoneNumberParser $phoneNumberParser,
        private readonly BusinessContext $business,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<TeamMemberData>
     */
    public function handle(UpdateTeamMemberInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $member = $this->members->findForBusiness($businessId, $input->staffMemberId);
            $profile = $this->profiles->findForStaffMember($businessId, $member->id);
            $phone = $this->parsedPhone($input->phone?->phone);

            $this->describeProfile($input, $profile);
            $transition = $this->changeLevel($input, $member);

            $invitations = $this->transactions->run(
                fn (): array => $this->apply($input, $member, $profile, $phone, $transition),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($invitations as $invitation) {
            $this->events->dispatch($invitation);
        }

        return UseCaseResponse::success($this->presenter->describe($member));
    }

    /**
     * @throws InvalidProfileJobTitle
     * @throws InvalidProfileAbout
     */
    private function describeProfile(UpdateTeamMemberInput $input, StaffProfile $profile): void
    {
        if ($input->jobTitle !== null) {
            $profile->changeJobTitle($input->toJobTitle($input->jobTitle));
        }

        if ($input->about !== null) {
            $profile->changeAbout($input->toAbout($input->about));
        }
    }

    /**
     * @throws OwnerLevelIsFixed
     * @throws InvalidTeamLevel
     */
    private function changeLevel(UpdateTeamMemberInput $input, StaffMember $member): AccessTransition
    {
        if ($input->level === null) {
            return AccessTransition::Unchanged;
        }

        return $member->changeRole($input->toLevel($input->level));
    }

    /**
     * @return list<TeamMemberInvited>
     */
    private function apply(
        UpdateTeamMemberInput $input,
        StaffMember $member,
        StaffProfile $profile,
        ?PhoneNumber $phone,
        AccessTransition $transition,
    ): array {
        if ($input->name !== null) {
            $this->accounts->rename($member->accountId, $input->name);
        }

        $this->profiles->save($profile);

        if ($input->phone !== null) {
            $this->phones->replaceForProfile($profile->id, $phone);
        }

        if ($input->level !== null) {
            $this->members->save($member);
        }

        return $this->invitationsFor($member, $transition);
    }

    /**
     * @return list<TeamMemberInvited>
     */
    private function invitationsFor(StaffMember $member, AccessTransition $transition): array
    {
        if ($transition !== AccessTransition::Granted) {
            return [];
        }

        return $member->invitationFor($this->provisioner->issueTemporaryPassword($member->accountId));
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
