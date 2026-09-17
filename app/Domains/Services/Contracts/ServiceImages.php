<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

use App\Domains\Services\Exceptions\ServiceNotFound;

interface ServiceImages
{
    public function urlFor(string $businessId, string $serviceId): ?string;

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $serviceIds): array;

    /**
     * @throws ServiceNotFound
     */
    public function replace(string $businessId, string $serviceId, string $sourcePath, string $fileName): string;

    /**
     * @throws ServiceNotFound
     */
    public function remove(string $businessId, string $serviceId): void;

    /**
     * @throws ServiceNotFound
     */
    public function copy(string $businessId, string $sourceServiceId, string $targetServiceId): void;
}
