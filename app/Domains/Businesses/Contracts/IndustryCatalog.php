<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

interface IndustryCatalog
{
    public function exists(string $industryId): bool;
}
