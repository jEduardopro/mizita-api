<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\TeamMemberInvited;

final readonly class InvitedTeamMember
{
    /**
     * @param  list<TeamMemberInvited>  $events
     */
    public function __construct(
        public StaffMember $member,
        public array $events,
    ) {}
}
