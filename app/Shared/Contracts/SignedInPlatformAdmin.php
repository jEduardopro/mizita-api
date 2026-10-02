<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface SignedInPlatformAdmin
{
    /**
     * @return array{name: string, email: string}|null
     */
    public function describe(): ?array;
}
