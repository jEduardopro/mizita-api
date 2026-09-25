<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Events\TeamMemberInvited;
use SensitiveParameter;

final readonly class SendTeamInvitationInput
{
    public function __construct(
        public string $businessId,
        public string $accountId,
        #[SensitiveParameter]
        public ?string $temporaryPassword,
    ) {}

    public static function fromEvent(TeamMemberInvited $event): self
    {
        return new self(
            businessId: $event->businessId,
            accountId: $event->accountId,
            temporaryPassword: $event->temporaryPassword,
        );
    }
}
