<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\MembershipRole;

final readonly class AccountBusinessData
{
    public function __construct(
        public BusinessData $business,
        public MembershipRole $role,
        public bool $isCurrent,
    ) {}
}
