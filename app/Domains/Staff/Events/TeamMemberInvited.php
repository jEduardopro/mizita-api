<?php

declare(strict_types=1);

namespace App\Domains\Staff\Events;

use SensitiveParameter;

final readonly class TeamMemberInvited
{
    public function __construct(
        public string $staffMemberId,
        public string $businessId,
        public string $accountId,
        #[SensitiveParameter]
        public ?string $temporaryPassword,
    ) {}
}
