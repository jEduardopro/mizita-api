<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;

interface BusinessRepository
{
    /**
     * @throws BusinessNotFound
     */
    public function findById(string $id): Business;

    public function existsByName(string $name): bool;

    /**
     * @return list<string>
     */
    public function slugsMatching(string $base): array;

    /**
     * @throws BusinessNameAlreadyTaken
     * @throws BusinessSlugAlreadyTaken
     */
    public function save(Business $business): void;

    /**
     * @throws BusinessNotFound
     */
    public function delete(string $id): void;
}
