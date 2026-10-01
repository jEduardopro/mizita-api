<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessSelection
{
    public function selectedBusinessIdFor(string $accountId): ?string;

    public function rememberFor(string $accountId, string $businessId): void;

    public function forgetFor(string $accountId): void;
}
