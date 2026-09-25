<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Staff\Contracts\BusinessDirectory;

final class BusinessesBusinessDirectory implements BusinessDirectory
{
    public function __construct(
        private readonly BusinessRepository $businesses,
    ) {}

    public function nameOf(string $businessId): string
    {
        return $this->businesses->findById($businessId)->name();
    }
}
