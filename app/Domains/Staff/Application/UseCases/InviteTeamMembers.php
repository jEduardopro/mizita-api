<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\InvitedTeamMember;
use App\Domains\Staff\Application\Dtos\InviteTeamMembersInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Dtos\TeamMemberInvitationInput;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Contracts\TeamAccountProvisioner;
use App\Domains\Staff\Contracts\TeamAccountSharing;
use App\Domains\Staff\Contracts\TeamAllowance;
use App\Domains\Staff\Contracts\TeamRoster;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\TeamMemberAlreadyExists;
use App\Domains\Staff\Exceptions\TeamRequiresCompletePlan;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final class InviteTeamMembers
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly StaffProfileRepository $profiles,
        private readonly TeamRoster $roster,
        private readonly TeamAccountProvisioner $accounts,
        private readonly TeamAccountSharing $sharing,
        private readonly TeamAllowance $allowance,
        private readonly TeamMemberPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<list<TeamMemberData>>
     */
    public function handle(InviteTeamMembersInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->ensurePlanIncludesTeam($businessId);
            $this->ensureNobodyIsAlreadyOnTeam($businessId, $input->emails());

            $invited = $this->transactions->run(
                fn (): array => $this->inviteAll($businessId, $input),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        foreach ($this->eventsOf($invited) as $event) {
            $this->events->dispatch($event);
        }

        return UseCaseResponse::success($this->presenter->describeMany($businessId, $this->membersOf($invited)));
    }

    /**
     * @throws TeamRequiresCompletePlan
     */
    private function ensurePlanIncludesTeam(string $businessId): void
    {
        if (! $this->allowance->includesTeam($businessId)) {
            throw TeamRequiresCompletePlan::for($businessId);
        }
    }

    /**
     * @param  list<string>  $emails
     *
     * @throws TeamMemberAlreadyExists
     */
    private function ensureNobodyIsAlreadyOnTeam(string $businessId, array $emails): void
    {
        $alreadyOnTeam = $this->roster->emailsAlreadyOnTeam($businessId, $emails);

        if ($alreadyOnTeam !== []) {
            throw TeamMemberAlreadyExists::withEmail($alreadyOnTeam[0]);
        }
    }

    /**
     * @return list<InvitedTeamMember>
     */
    private function inviteAll(string $businessId, InviteTeamMembersInput $input): array
    {
        $now = $this->clock->now();

        return array_map(
            fn (TeamMemberInvitationInput $invitation): InvitedTeamMember => $this->invite($businessId, $invitation, $now),
            $input->members,
        );
    }

    private function invite(string $businessId, TeamMemberInvitationInput $invitation, DateTimeImmutable $now): InvitedTeamMember
    {
        $level = $invitation->toLevel();
        $account = $this->accounts->provision($level, $invitation->trimmedName(), $invitation->toEmail()->value);

        $member = StaffMember::register(
            id: $this->ids->next(),
            businessId: $businessId,
            accountId: $account->accountId,
            now: $now,
            role: $level,
        );

        $this->members->save($member);

        $this->profiles->save(StaffProfile::create(
            id: $this->ids->next(),
            businessId: $businessId,
            staffMemberId: $member->id,
            now: $now,
        ));

        return new InvitedTeamMember(
            $member,
            $member->invitationFor($account->temporaryPassword ?? $this->temporaryPasswordFor($member)),
        );
    }

    /**
     * @throws StaffMemberNotFound
     */
    private function temporaryPasswordFor(StaffMember $member): ?string
    {
        $sharing = $this->sharing->sharingOf($member->accountId, $member->businessId);

        if (! $member->mayManageTemporaryPassword($sharing)) {
            return null;
        }

        return $this->accounts->issueTemporaryPassword($member->accountId);
    }

    /**
     * @param  list<InvitedTeamMember>  $invited
     * @return list<StaffMember>
     */
    private function membersOf(array $invited): array
    {
        return array_map(
            static fn (InvitedTeamMember $invitedMember): StaffMember => $invitedMember->member,
            $invited,
        );
    }

    /**
     * @param  list<InvitedTeamMember>  $invited
     * @return list<TeamMemberInvited>
     */
    private function eventsOf(array $invited): array
    {
        return array_merge(...array_map(
            static fn (InvitedTeamMember $invitedMember): array => $invitedMember->events,
            $invited,
        ));
    }
}
