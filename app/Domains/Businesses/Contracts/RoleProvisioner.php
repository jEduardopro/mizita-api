<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

interface RoleProvisioner
{
    public function provisionFor(string $businessId): void;
}
