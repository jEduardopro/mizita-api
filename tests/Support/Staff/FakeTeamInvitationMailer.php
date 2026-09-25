<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\TeamInvitationMailer;
use App\Domains\Staff\ValueObjects\TeamInvitation;
use Throwable;

final class FakeTeamInvitationMailer implements TeamInvitationMailer
{
    /**
     * @var list<TeamInvitation>
     */
    public array $sent = [];

    private ?Throwable $failure = null;

    public function failWith(Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    public function send(TeamInvitation $invitation): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->sent[] = $invitation;
    }
}
