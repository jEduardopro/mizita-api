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
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;

/**
 * Three deliberate absences:
 *
 * - No BusinessContext. The row it writes is what resolves the tenant, so
 *   businessId arrives as an argument. This is the single named exception to
 *   the rule that a tenant-scoped use case reads its business from the context.
 * - No TransactionManager. It runs inside the caller's transaction, so opening
 *   its own would split the two writes into separately committed halves.
 * - No Dispatcher. It hands the event back inside StaffMemberRegistration for
 *   the caller to announce once that transaction has committed.
 */
final class RegisterBusinessOwner
{
    public function __construct(
        private readonly StaffMemberRepository $staffMembers,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    public function handle(RegisterBusinessOwnerInput $input): StaffMemberRegistration
    {
        // A courtesy check, not the enforcement: it turns signing up twice into
        // a clean refusal rather than a constraint violation. Two concurrent
        // signups both pass here, and the partial unique index on (account_id)
        // where role = 'owner' is what rejects the loser.
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
