<?php

declare(strict_types=1);

namespace App\Domains\Services\Contracts;

use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNameAlreadyTaken;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\ServiceSlugAlreadyTaken;
use App\Domains\Services\ValueObjects\ServiceQuery;
use App\Shared\ValueObjects\Paginated;

interface ServiceRepository
{
    /**
     * @return Paginated<Service>
     */
    public function search(string $businessId, ServiceQuery $query): Paginated;

    /**
     * @return list<Service>
     */
    public function activeForBusiness(string $businessId): array;

    /**
     * @throws ServiceNotFound
     */
    public function findForBusiness(string $businessId, string $id): Service;

    /**
     * @throws ServiceNotFound
     */
    public function findIncludingArchived(string $businessId, string $id): Service;

    public function existsByName(string $businessId, string $name): bool;

    /**
     * @return list<string>
     */
    public function slugsMatching(string $businessId, string $base): array;

    /**
     * @return list<string>
     */
    public function namesMatching(string $businessId, string $base): array;

    /**
     * @throws ServiceNameAlreadyTaken
     * @throws ServiceSlugAlreadyTaken
     */
    public function save(Service $service): void;

    /**
     * @throws ServiceNotFound
     */
    public function delete(string $businessId, string $id): void;
}
