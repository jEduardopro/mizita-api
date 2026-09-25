<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

final readonly class CheckAccountMembershipsRemovalInput
{
    public function __construct(
        public string $accountId,
    ) {}
}
