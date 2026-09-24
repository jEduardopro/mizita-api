<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

final readonly class RenameAccountInput
{
    public function __construct(
        public string $accountId,
        public string $name,
    ) {}
}
