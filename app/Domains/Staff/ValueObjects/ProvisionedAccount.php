<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

use SensitiveParameter;

final readonly class ProvisionedAccount
{
    public function __construct(
        public string $accountId,
        #[SensitiveParameter]
        public ?string $temporaryPassword,
    ) {}
}
