<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface PausedBusinessAccess
{
    /**
     * @return list<string>
     */
    public function pausedBusinessIdsFor(string $accountId): array;
}
