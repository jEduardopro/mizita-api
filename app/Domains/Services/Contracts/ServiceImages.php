<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

use App\Domains\Services\Exceptions\ServiceNotFound;

interface ServiceImages
{
    public function urlFor(string $serviceId): ?string;

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, string>
     */
    public function urlsFor(array $serviceIds): array;

    /**
     * @throws ServiceNotFound
     */
    public function replace(string $serviceId, string $sourcePath, string $fileName): string;

    /**
     * @throws ServiceNotFound
     */
    public function remove(string $serviceId): void;

    /**
     * @throws ServiceNotFound
     */
    public function copy(string $sourceServiceId, string $targetServiceId): void;
}
