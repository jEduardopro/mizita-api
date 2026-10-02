<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface ImpersonationStatus
{
    public function isActive(): bool;

    /**
     * @return array{business_name: string, owner_name: string, expires_at: string}|null
     */
    public function describe(): ?array;
}
