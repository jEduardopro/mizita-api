<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessMembership
{
    /**
     * @return list<string>
     */
    public function businessIdsFor(string $accountId): array;
}
