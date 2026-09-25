<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Listeners;

use App\Domains\Staff\Application\Dtos\SendTeamInvitationInput;
use App\Domains\Staff\Application\UseCases\SendTeamInvitation;
use App\Domains\Staff\Events\TeamMemberInvited;

final class NotifyInvitedTeamMember
{
    public function __construct(
        private readonly SendTeamInvitation $sendTeamInvitation,
    ) {}

    public function handle(TeamMemberInvited $event): void
    {
        $this->sendTeamInvitation->handle(SendTeamInvitationInput::fromEvent($event))->value();
    }
}
