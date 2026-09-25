<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

final readonly class RemoveAccountMembershipsInput
{
    public function __construct(
        public string $accountId,
    ) {}
}
