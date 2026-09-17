<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use App\Domains\Customers\Exceptions\CustomerNotFound;

interface CustomerPhotos
{
    public function urlFor(string $businessId, string $customerId): ?string;

    /**
     * @param  list<string>  $customerIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $customerIds): array;

    /**
     * @throws CustomerNotFound
     */
    public function replace(string $businessId, string $customerId, string $sourcePath, string $fileName): void;

    /**
     * @throws CustomerNotFound
     */
    public function remove(string $businessId, string $customerId): void;
}
