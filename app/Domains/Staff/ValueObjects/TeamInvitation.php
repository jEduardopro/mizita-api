<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use SensitiveParameter;

final readonly class TeamInvitation
{
    public function __construct(
        public string $accountId,
        public string $businessName,
        #[SensitiveParameter]
        public ?string $temporaryPassword,
    ) {}
}
