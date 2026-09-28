<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\RegisterBusinessOwnerInput;
use App\Domains\Staff\Application\Dtos\StaffMemberData;
use App\Domains\Staff\Application\Dtos\StaffMemberRegistration;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Events\StaffMemberRegistered;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\BookingSlug;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class RegisterBusinessOwner
{
    public function __construct(
        private readonly StaffMemberRepository $staffMembers,
        private readonly StaffProfileRepository $profiles,
        private readonly AccountDirectory $accounts,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
    ) {}

    /**
     * @return UseCaseResponse<StaffMemberRegistration>
     */
    public function handle(RegisterBusinessOwnerInput $input): UseCaseResponse
    {
        try {
            $registration = $this->register($input);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success($registration);
    }

    /**
     * @throws AccountAlreadyOwnsBusiness
     * @throws StaffMemberNotFound
     */
    private function register(RegisterBusinessOwnerInput $input): StaffMemberRegistration
    {
        if ($this->staffMembers->ownsAnyBusiness($input->accountId)) {
            throw AccountAlreadyOwnsBusiness::forAccount($input->accountId);
        }

        $now = $this->clock->now();

        $owner = StaffMember::registerOwner(
            id: $this->ids->next(),
            businessId: $input->businessId,
            accountId: $input->accountId,
            now: $now,
        );

        $profile = StaffProfile::createForOwner(
            id: $this->ids->next(),
            businessId: $owner->businessId,
            staffMemberId: $owner->id,
            bookingSlug: BookingSlug::fromName($this->nameOf($input->accountId)),
            now: $now,
        );

        $this->transactions->run(function () use ($owner, $profile): void {
            $this->staffMembers->save($owner);
            $this->profiles->save($profile);
        });

        return new StaffMemberRegistration(
            member: StaffMemberData::fromEntity($owner),
            events: [new StaffMemberRegistered($owner->id, $owner->businessId, $owner->role())],
        );
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function nameOf(string $accountId): string
    {
        $account = $this->accounts->describe([$accountId])[0]
            ?? throw StaffMemberNotFound::forAccount($accountId);

        return $account->name;
    }
}
