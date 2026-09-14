<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Industries\Contracts\IndustryRepository;

final class IndustriesIndustryCatalog implements IndustryCatalog
{
    public function __construct(
        private readonly IndustryRepository $industries,
    ) {}

    public function exists(string $industryId): bool
    {
        return $this->industries->isSelectable($industryId);
    }
}
