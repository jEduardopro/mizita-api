<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

final readonly class RegisterBusinessOwnerInput
{
    public function __construct(
        public string $businessId,
        public string $accountId,
    ) {}
}
