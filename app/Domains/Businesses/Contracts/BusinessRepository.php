<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use DateTimeImmutable;

interface BusinessRepository
{
    /**
     * @throws BusinessNotFound
     */
    public function findById(string $id): Business;

    /**
     * @throws BusinessNotFound
     */
    public function findBySlug(string $slug): Business;

    /**
     * @param  list<string>  $ids
     * @return list<Business>
     */
    public function findManyByIds(array $ids): array;

    public function findClosedById(string $id): ?Business;

    public function findClosedOwnedBy(string $accountId): ?Business;

    /**
     * @return list<string>
     */
    public function idsDueForPurge(DateTimeImmutable $cutoff): array;

    public function existsByName(string $name): bool;

    public function existsBySlug(string $slug): bool;

    /**
     * @return list<string>
     */
    public function slugsMatching(string $base): array;

    /**
     * @throws BusinessNameAlreadyTaken
     * @throws BusinessSlugAlreadyTaken
     * @throws InvalidBusinessOwner
     */
    public function save(Business $business): void;

    /**
     * @throws BusinessNotFound
     */
    public function delete(string $id): void;
}
