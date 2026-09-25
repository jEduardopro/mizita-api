<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\DuplicateTeamInvitationEmail;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamInvitation;
use App\Domains\Staff\Exceptions\InvalidTeamLevel;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;

final readonly class InviteTeamMembersInput
{
    public const MAXIMUM_MEMBERS = 20;

    /**
     * @param  list<TeamMemberInvitationInput>  $members
     */
    public function __construct(
        public array $members,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        $rows = $payload['members'] ?? [];

        return new self(
            members: is_array($rows)
                ? array_values(array_map(TeamMemberInvitationInput::fromPayload(...), $rows))
                : [],
        );
    }

    /**
     * @throws InvalidTeamInvitation
     * @throws InvalidProfileName
     * @throws InvalidTeamMemberEmail
     * @throws InvalidTeamLevel
     * @throws DuplicateTeamInvitationEmail
     */
    public function validate(): void
    {
        $this->validateMemberCount();
        $this->validateMembers();
        $this->validateEmailsAreDistinct();
    }

    /**
     * @return list<string>
     *
     * @throws InvalidTeamMemberEmail
     */
    public function emails(): array
    {
        return array_map(
            static fn (TeamMemberInvitationInput $member): string => $member->toEmail()->value,
            $this->members,
        );
    }

    private function validateMemberCount(): void
    {
        if ($this->members === []) {
            throw InvalidTeamInvitation::empty();
        }

        if (count($this->members) > self::MAXIMUM_MEMBERS) {
            throw InvalidTeamInvitation::tooManyMembers(self::MAXIMUM_MEMBERS);
        }
    }

    private function validateMembers(): void
    {
        foreach ($this->members as $member) {
            $member->validate();
        }
    }

    private function validateEmailsAreDistinct(): void
    {
        $seen = [];

        foreach ($this->emails() as $email) {
            if (isset($seen[$email])) {
                throw DuplicateTeamInvitationEmail::for($email);
            }

            $seen[$email] = true;
        }
    }
}
