<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

interface TenantDataEraser
{
    public function eraseFilesOf(string $businessId): void;

    public function eraseRecordsOf(string $businessId): void;
}
