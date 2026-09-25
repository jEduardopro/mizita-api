<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\ValueObjects\TeamInvitation;

interface TeamInvitationMailer
{
    public function send(TeamInvitation $invitation): void;
}
