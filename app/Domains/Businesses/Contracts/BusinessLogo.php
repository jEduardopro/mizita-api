<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Exceptions\BusinessNotFound;

interface BusinessLogo
{
    public function urlFor(string $businessId): ?string;

    /**
     * @throws BusinessNotFound
     */
    public function replace(string $businessId, string $sourcePath, string $fileName): string;

    /**
     * @throws BusinessNotFound
     */
    public function remove(string $businessId): void;
}
