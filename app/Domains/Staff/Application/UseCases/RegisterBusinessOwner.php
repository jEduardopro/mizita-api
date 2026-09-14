<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\RegisterBusinessOwnerInput;
use App\Domains\Staff\Application\Dtos\StaffMemberData;
use App\Domains\Staff\Application\Dtos\StaffMemberRegistration;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\StaffMemberRegistered;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class RegisterBusinessOwner
{
    public function __construct(
        private readonly StaffMemberRepository $staffMembers,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
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
     */
    private function register(RegisterBusinessOwnerInput $input): StaffMemberRegistration
    {
        if ($this->staffMembers->ownsAnyBusiness($input->accountId)) {
            throw AccountAlreadyOwnsBusiness::forAccount($input->accountId);
        }

        $owner = StaffMember::registerOwner(
            id: $this->ids->next(),
            businessId: $input->businessId,
            accountId: $input->accountId,
            now: $this->clock->now(),
        );

        $this->staffMembers->save($owner);

        return new StaffMemberRegistration(
            member: StaffMemberData::fromEntity($owner),
            events: [new StaffMemberRegistered($owner->id, $owner->businessId, $owner->role())],
        );
    }
}
